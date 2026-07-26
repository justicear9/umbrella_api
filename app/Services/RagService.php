<?php

namespace App\Services;

use App\Models\Briefing;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RagService
{
    public function __construct(private OpenAIService $openai) {}

    /**
     * @return list<array{chunk: DocumentChunk, score: float}>
     */
    public function retrieve(string $query, int $limit = 8, ?int $documentId = null): array
    {
        $searchQuery = $this->normalizeSearchQuery($query);
        $embeddings = $this->openai->embed([$searchQuery]);
        $queryVec = $embeddings[0] ?? null;
        if (! $queryVec) {
            return [];
        }

        /** @var Collection<int, DocumentChunk> $chunks */
        $chunks = DocumentChunk::query()
            ->whereNotNull('embedding')
            ->when($documentId, fn ($q) => $q->where('document_id', $documentId))
            ->with('document:id,title')
            ->get();

        $keywords = $this->keywords($searchQuery);
        $scored = [];

        foreach ($chunks as $chunk) {
            $cosine = $this->cosineSimilarity($queryVec, $chunk->embedding ?? []);
            if ($cosine < 0.12) {
                continue;
            }

            $boost = $this->keywordBoost($chunk->content, $keywords);
            $score = $cosine + $boost;
            $scored[] = ['chunk' => $chunk, 'score' => $score, 'cosine' => $cosine];
        }

        // Lexical safety net: ensure strong keyword matches aren't buried by embedding noise.
        if ($keywords !== []) {
            $lexical = DocumentChunk::query()
                ->whereNotNull('embedding')
                ->when($documentId, fn ($q) => $q->where('document_id', $documentId))
                ->with('document:id,title')
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('content', 'like', '%'.$kw.'%');
                    }
                })
                ->limit(24)
                ->get();

            $seen = [];
            foreach ($scored as $row) {
                $seen[$row['chunk']->id] = true;
            }

            foreach ($lexical as $chunk) {
                if (isset($seen[$chunk->id])) {
                    continue;
                }
                $boost = $this->keywordBoost($chunk->content, $keywords);
                if ($boost < 0.08) {
                    continue;
                }
                $cosine = $this->cosineSimilarity($queryVec, $chunk->embedding ?? []);
                $scored[] = [
                    'chunk' => $chunk,
                    'score' => max($cosine, 0.2) + $boost + 0.05,
                    'cosine' => $cosine,
                ];
                $seen[$chunk->id] = true;
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_map(
            fn ($row) => ['chunk' => $row['chunk'], 'score' => $row['score']],
            $scored
        ), 0, $limit);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{
     *   response: string,
     *   citations: list<array{document: string, pages: string, excerpt: string, document_id?: int|null}>,
     *   chart: array<string, mixed>|null,
     *   footnotes: list<array{marker: string, document_id: int, document: string, section: string, page: int, pages: string, excerpt: string, label: string}>
     * }
     */
    public function answer(string $question, array $history = []): array
    {
        $manifesto = $this->manifestoDocument();
        $hits = $this->retrieve($question, 6);
        $manifestoHits = $manifesto
            ? $this->retrieve($question, 4, (int) $manifesto->id)
            : [];
        $hits = $this->mergeHits($hits, $manifestoHits, 10);

        $contextBlocks = [];
        $manifestoBlocks = [];
        $citations = [];

        foreach ($hits as $hit) {
            /** @var DocumentChunk $chunk */
            $chunk = $hit['chunk'];
            $pages = $chunk->page_start === $chunk->page_end
                ? (string) $chunk->page_start
                : "{$chunk->page_start}-{$chunk->page_end}";
            $docTitle = $chunk->document?->title ?? 'Document';
            $excerpt = mb_substr($chunk->content, 0, 900);
            $block = "[{$docTitle} | pages {$pages} | document_id {$chunk->document_id}]\n{$chunk->content}";
            $contextBlocks[] = $block;
            if ($manifesto && (int) $chunk->document_id === (int) $manifesto->id) {
                $manifestoBlocks[] = $block;
            }
            $citations[] = [
                'document' => $docTitle,
                'document_id' => $chunk->document_id,
                'pages' => $pages,
                'excerpt' => mb_substr(preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt, 0, 180),
            ];
        }

        $briefingHints = Briefing::published()
            ->latest('published_at')
            ->limit(8)
            ->get(['title', 'category', 'summary', 'talking_points', 'key_stats'])
            ->map(fn (Briefing $b) => [
                'title' => $b->title,
                'category' => $b->category,
                'summary' => $b->summary,
                'talking_points' => $b->talking_points,
                'key_stats' => $b->key_stats,
            ])
            ->all();

        $wantsVisual = $this->wantsVisual($question);

        $system = <<<'PROMPT'
You are Comrade AI, the NDC Communicators coach for Ghana's ruling National Democratic Congress.
Answer using the provided SOURCE CONTEXT and briefing cards.

Critical rules for charts/graphs/tables/figures:
- Digested PDFs often store chart/graph data as surrounding text, captions, labels, and numbers — not as images.
- If the user asks for a "graph", "chart", "figure", or "table" and the sources contain the underlying GDP/fiscal figures, YOU MUST answer from those figures.
- Present a clear communicator summary (bullets). Do NOT say you lack the content merely because a visual chart image is absent.
- Only say you don't have it when the sources truly lack the topic/numbers.

Manifesto footnotes (policy delivery):
- Whenever the answer communicates policy delivery, achievements, implementation progress, fulfilled pledges, or wins, you MUST link each delivery claim to the matching 2024 NDC Manifesto commitment.
- Place inline markers like [M1], [M2] immediately after the delivery claim in the response markdown.
- Fill manifesto_footnotes using ONLY the MANIFESTO SOURCE blocks. page must be an integer page number from those headers. section is the nearest manifesto heading/commitment title. excerpt is a short quote of the pledge. label is a short communicator label (e.g. "24-Hour Economy pledge").
- Never invent manifesto pages or sections. If no manifesto source matches, leave manifesto_footnotes as [].
- If the answer is not about delivery/achievement, manifesto_footnotes may be [].

Style:
- Be concise, punchy, and radio/TV ready.
- Prefer Ghana cedi/figures exactly as stated in sources.
- Cite pages inline like (pages 12-14) when using specific facts.
- Never invent statistics.

Return STRICT JSON:
{
  "response": "markdown answer for the communicator",
  "chart": null,
  "manifesto_footnotes": []
}
manifesto_footnotes item shape:
{
  "marker": "M1",
  "section": "section or commitment title",
  "page": 32,
  "excerpt": "short quote of the manifesto commitment",
  "label": "short label"
}
When (and only when) chart_requested is true AND sources contain comparable numeric points, set chart to:
{
  "type": "bar" | "line",
  "title": "short chart title",
  "unit": "%" or "GHȼbn" or similar,
  "series": [
    {
      "name": "series label",
      "points": [{"label": "2025", "value": 6.0}, {"label": "Q1 2026", "value": 6.4}]
    }
  ]
}
Rules for chart:
- Use only numbers present in SOURCE CONTEXT (no invented points).
- Prefer 2–8 points. Multiple series only when clearly comparable in sources.
- Prefer "bar" for period comparisons; "line" for time trends with 3+ points.
- If chart_requested is false, chart must be null.
- If chart_requested is true but numbers are insufficient, chart must be null and explain the figures in response text.
PROMPT;

        $sourceBlob = $contextBlocks === []
            ? '(No matching source chunks retrieved.)'
            : implode("\n\n---\n\n", $contextBlocks);
        $manifestoBlob = $manifestoBlocks === []
            ? '(No manifesto chunks retrieved.)'
            : implode("\n\n---\n\n", $manifestoBlocks);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "chart_requested: ".($wantsVisual ? 'true' : 'false')."\n\nBRIEFING CARDS:\n".json_encode($briefingHints, JSON_UNESCAPED_UNICODE)."\n\nSOURCE CONTEXT:\n".$sourceBlob."\n\nMANIFESTO SOURCE (for footnotes):\n".$manifestoBlob],
        ];

        foreach (array_slice($history, -6) as $msg) {
            if (in_array($msg['role'] ?? '', ['user', 'assistant'], true)) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => (string) $msg['content'],
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $decoded = $this->openai->chatJson($messages, ['max_tokens' => 1800, 'temperature' => 0.2]);
        $response = trim((string) ($decoded['response'] ?? ''));
        if ($response === '') {
            $response = 'I could not form an answer from the digested sources. Try a more specific question.';
        }

        $footnotes = $this->normalizeManifestoFootnotes(
            $decoded['manifesto_footnotes'] ?? null,
            $manifestoHits,
            $manifesto
        );

        if ($footnotes === [] && $manifesto && $manifestoHits !== [] && $this->looksLikeDelivery($question, $response)) {
            $footnotes = $this->synthesizeManifestoFootnotes($manifestoHits, $manifesto);
        }

        if ($footnotes !== []) {
            $response = $this->ensureFootnoteMarkers($response, $footnotes);
        }

        return [
            'response' => $response,
            'citations' => $citations,
            'chart' => $this->normalizeChart($decoded['chart'] ?? null, $wantsVisual),
            'footnotes' => $footnotes,
        ];
    }

    private function manifestoDocument(): ?Document
    {
        return Document::query()
            ->where('status', 'ready')
            ->where('title', 'like', '%Manifesto%')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  list<array{chunk: DocumentChunk, score: float}>  $primary
     * @param  list<array{chunk: DocumentChunk, score: float}>  $extra
     * @return list<array{chunk: DocumentChunk, score: float}>
     */
    private function mergeHits(array $primary, array $extra, int $limit): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($primary, $extra) as $row) {
            $id = $row['chunk']->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function looksLikeDelivery(string $question, string $response): bool
    {
        $hay = $question."\n".$response;

        return (bool) preg_match(
            '/\b(deliver(?:ed|y|ies|ing)?|achiev(?:e|ed|ement|ements)|implement(?:ed|ation|ing)?|fulfil(?:ed|ment|s)?|pledge(?:d|s)?|promis(?:e|ed|es)|commit(?:ment|ments|ted)?|roll(?:ed)?\s*out|launch(?:ed|es)?|complet(?:ed|ion)|progress|wins?|on[- ]track)\b/iu',
            $hay
        );
    }

    /**
     * @param  list<array{chunk: DocumentChunk, score: float}>  $manifestoHits
     * @return list<array{marker: string, document_id: int, document: string, section: string, page: int, pages: string, excerpt: string, label: string}>
     */
    private function normalizeManifestoFootnotes(mixed $raw, array $manifestoHits, ?Document $manifesto): array
    {
        if (! $manifesto || ! is_array($raw) || $manifestoHits === []) {
            return [];
        }

        $allowedPages = [];
        foreach ($manifestoHits as $hit) {
            /** @var DocumentChunk $chunk */
            $chunk = $hit['chunk'];
            $start = (int) $chunk->page_start;
            $end = (int) ($chunk->page_end ?: $chunk->page_start);
            for ($p = $start; $p <= $end; $p++) {
                $allowedPages[$p] = $chunk;
            }
        }

        $out = [];
        $used = [];
        $i = 1;
        foreach ($raw as $item) {
            if (! is_array($item) || count($out) >= 3) {
                break;
            }
            $page = (int) ($item['page'] ?? 0);
            if ($page < 1 || ! isset($allowedPages[$page]) || isset($used[$page])) {
                continue;
            }
            /** @var DocumentChunk $chunk */
            $chunk = $allowedPages[$page];
            $marker = trim((string) ($item['marker'] ?? ''));
            if ($marker === '' || ! preg_match('/^M\d+$/i', $marker)) {
                $marker = 'M'.$i;
            }
            $section = trim((string) ($item['section'] ?? ''));
            if ($section === '') {
                $section = $this->guessSection($chunk);
            }
            $excerpt = trim((string) ($item['excerpt'] ?? ''));
            if ($excerpt === '') {
                $excerpt = mb_substr(preg_replace('/\s+/', ' ', $chunk->content) ?? $chunk->content, 0, 160);
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                $label = $section;
            }

            $out[] = [
                'marker' => strtoupper($marker),
                'document_id' => (int) $manifesto->id,
                'document' => $manifesto->title,
                'section' => mb_substr($section, 0, 140),
                'page' => $page,
                'pages' => (string) $page,
                'excerpt' => mb_substr($excerpt, 0, 220),
                'label' => mb_substr($label, 0, 100),
            ];
            $used[$page] = true;
            $i++;
        }

        return $out;
    }

    /**
     * @param  list<array{chunk: DocumentChunk, score: float}>  $manifestoHits
     * @return list<array{marker: string, document_id: int, document: string, section: string, page: int, pages: string, excerpt: string, label: string}>
     */
    private function synthesizeManifestoFootnotes(array $manifestoHits, Document $manifesto): array
    {
        $out = [];
        $i = 1;
        foreach (array_slice($manifestoHits, 0, 2) as $hit) {
            /** @var DocumentChunk $chunk */
            $chunk = $hit['chunk'];
            $page = (int) $chunk->page_start;
            if ($page < 1) {
                continue;
            }
            $section = $this->guessSection($chunk);
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', $chunk->content) ?? $chunk->content, 0, 160);
            $out[] = [
                'marker' => 'M'.$i,
                'document_id' => (int) $manifesto->id,
                'document' => $manifesto->title,
                'section' => mb_substr($section, 0, 140),
                'page' => $page,
                'pages' => (string) $page,
                'excerpt' => $excerpt,
                'label' => mb_substr($section, 0, 100),
            ];
            $i++;
        }

        return $out;
    }

    /**
     * @param  list<array{marker: string, document_id: int, document: string, section: string, page: int, pages: string, excerpt: string, label: string}>  $footnotes
     */
    private function ensureFootnoteMarkers(string $response, array $footnotes): string
    {
        $missing = [];
        foreach ($footnotes as $fn) {
            $marker = $fn['marker'];
            if (! preg_match('/\['.preg_quote($marker, '/').'\]/i', $response)) {
                $missing[] = '['.$marker.']';
            }
        }
        if ($missing === []) {
            return $response;
        }

        return rtrim($response)."\n\n".implode(' ', $missing);
    }

    private function guessSection(DocumentChunk $chunk): string
    {
        $lines = preg_split('/\R/u', trim($chunk->content)) ?: [];
        foreach (array_slice($lines, 0, 10) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
            if ($line === '' || mb_strlen($line) < 4 || mb_strlen($line) > 120) {
                continue;
            }
            if (preg_match('/^(chapter\s+\d+|introduction|acronyms|message from)/iu', $line)) {
                return $line;
            }
            if (preg_match('/^\d+(\.\d+){0,3}\s+[A-Za-z]/', $line)) {
                return $line;
            }
            if (preg_match('/^[A-Z][A-Za-z0-9 ,&\-\/\'()]{3,90}$/', $line) && ! str_ends_with($line, '.')) {
                return $line;
            }
        }

        return '2024 NDC Manifesto commitment';
    }

    private function wantsVisual(string $question): bool
    {
        return (bool) preg_match(
            '/\b(graph|graphs|chart|charts|plot|plots|visual|figure|figures|diagram|bar\s*chart|line\s*chart)\b/i',
            $question
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeChart(mixed $chart, bool $wantsVisual): ?array
    {
        if (! $wantsVisual || ! is_array($chart)) {
            return null;
        }

        $type = strtolower((string) ($chart['type'] ?? 'bar'));
        if (! in_array($type, ['bar', 'line'], true)) {
            $type = 'bar';
        }

        $title = trim((string) ($chart['title'] ?? 'Performance'));
        $unit = trim((string) ($chart['unit'] ?? ''));
        $seriesIn = $chart['series'] ?? null;
        if (! is_array($seriesIn) || $seriesIn === []) {
            return null;
        }

        $series = [];
        foreach (array_slice($seriesIn, 0, 3) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $name = trim((string) ($s['name'] ?? 'Series'));
            $pointsIn = $s['points'] ?? null;
            if (! is_array($pointsIn) || $pointsIn === []) {
                continue;
            }
            $points = [];
            foreach (array_slice($pointsIn, 0, 8) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $label = trim((string) ($p['label'] ?? ''));
                if ($label === '' || ! isset($p['value']) || ! is_numeric($p['value'])) {
                    continue;
                }
                $points[] = [
                    'label' => mb_substr($label, 0, 24),
                    'value' => round((float) $p['value'], 2),
                ];
            }
            if (count($points) >= 2) {
                $series[] = ['name' => mb_substr($name, 0, 40), 'points' => $points];
            }
        }

        if ($series === []) {
            return null;
        }

        return [
            'type' => $type,
            'title' => mb_substr($title !== '' ? $title : 'Performance', 0, 80),
            'unit' => mb_substr($unit, 0, 16),
            'series' => $series,
        ];
    }

    /**
     * Drop visual-only words so embedding search targets the substance (GDP, inflation…).
     */
    private function normalizeSearchQuery(string $query): string
    {
        $q = trim($query);
        $q = preg_replace(
            '/\b(graph|graphs|chart|charts|figure|figures|diagram|plot|visual|picture|image)\b/iu',
            ' ',
            $q
        ) ?? $q;
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        $q = trim($q);

        // Keep original substance; add a light fiscal hint when GDP is mentioned.
        if (preg_match('/\bgdp\b/i', $q) && ! preg_match('/\bgrowth\b/i', $q)) {
            $q .= ' growth performance real GDP';
        }

        return $q !== '' ? $q : trim($query);
    }

    /**
     * @return list<string>
     */
    private function keywords(string $query): array
    {
        $stop = [
            'a', 'an', 'the', 'of', 'in', 'on', 'for', 'to', 'and', 'or', 'with', 'about',
            'summary', 'summarise', 'summarize', 'please', 'what', 'is', 'are', 'was', 'were',
            'me', 'my', 'give', 'show', 'tell',
        ];

        $parts = preg_split('/[^a-zA-Z0-9%+.-]+/u', Str::lower($query)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || in_array($p, $stop, true) || mb_strlen($p) < 3) {
                continue;
            }
            $out[] = $p;
        }

        // Prefer distinctive terms first.
        usort($out, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique(array_slice($out, 0, 8)));
    }

    /**
     * @param  list<string>  $keywords
     */
    private function keywordBoost(string $content, array $keywords): float
    {
        if ($keywords === []) {
            return 0.0;
        }

        $hay = Str::lower($content);
        $hits = 0;
        $weight = 0.0;
        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }
            if (str_contains($hay, Str::lower($kw))) {
                $hits++;
                // Stronger boost for GDP / growth style tokens.
                $weight += in_array($kw, ['gdp', 'growth', 'inflation', 'debt', 'fiscal'], true) ? 0.06 : 0.035;
            }
        }

        if ($hits === 0) {
            return 0.0;
        }

        return min(0.25, $weight);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
