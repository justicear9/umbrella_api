<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatWebVerifyService
{
    public function __construct(private OpenAIService $openai) {}

    public function enabled(): bool
    {
        return (bool) config('services.chat_web_verify.enabled', true);
    }

    /**
     * @param  list<string>  $roomLines
     * @return array{
     *   answer: string,
     *   footnotes: list<array{
     *     marker: string,
     *     document_id: int|null,
     *     document: string,
     *     section: string,
     *     page: int,
     *     pages: string,
     *     excerpt: string,
     *     label: string,
     *     url: string
     *   }>
     * }
     */
    public function verify(string $asker, string $question, array $roomLines): array
    {
        if (! $this->enabled()) {
            return ['answer' => '', 'footnotes' => []];
        }

        $domains = array_values(config('services.chat_web_verify.allowed_domains', []));
        $outletList = implode(', ', [
            'JoyNews (myjoyonline.com)',
            'CitiFM (citinewsroom.com)',
            'GTV / GBC (gbcghanaonline.com, gtvghana.com)',
            'TV3 / Three FM (3news.com)',
            'BBC (bbc.com)',
            'CNN (cnn.com)',
            'DW (dw.com)',
        ]);

        $transcript = $roomLines === []
            ? '(No earlier messages.)'
            : implode("\n", array_slice($roomLines, -15));

        $input = <<<PROMPT
You are Comrade AI verifying claims for NDC communicators in Ghana's National Chatroom.

Search ONLY these news outlets (hard allow-list already applied by the search tool):
{$outletList}

RECENT ROOM DISCUSSION:
{$transcript}

REQUEST FROM {$asker}:
{$question}

Instructions:
- Prefer verifying claims made in the room discussion when the request is about checking / clarifying / confirming something said.
- Be concise and radio/TV ready (short bullets OK).
- Cite facts using inline markers like [W1], [W2] that match the sources you used.
- If outlets disagree or evidence is thin, say so clearly — do not invent headlines or URLs.
- Ignore sources outside the allow-list.

Return STRICT JSON only:
{
  "summary": "markdown answer for the room, with [W1] markers where news claims are used",
  "sources": [
    {
      "marker": "W1",
      "outlet": "BBC",
      "title": "headline",
      "url": "https://...",
      "excerpt": "short supporting quote or paraphrase"
    }
  ]
}
PROMPT;

        try {
            $raw = $this->openai->responsesWithWebSearch($input, $domains, [
                'temperature' => 0.2,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chat web verify search failed', ['error' => $e->getMessage()]);

            return ['answer' => '', 'footnotes' => []];
        }

        $parsed = $this->parseJsonAnswer((string) ($raw['answer'] ?? ''));
        $summary = trim((string) ($parsed['summary'] ?? ''));
        if ($summary === '' && trim((string) ($raw['answer'] ?? '')) !== '') {
            // Model returned prose instead of JSON — still usable.
            $summary = trim((string) $raw['answer']);
        }

        $footnotes = $this->normalizeFootnotes(
            is_array($parsed['sources'] ?? null) ? $parsed['sources'] : [],
            is_array($raw['sources'] ?? null) ? $raw['sources'] : [],
            $domains
        );

        if ($footnotes !== [] && $summary !== '') {
            $summary = $this->ensureWebMarkers($summary, $footnotes);
        }

        return [
            'answer' => $summary,
            'footnotes' => $footnotes,
        ];
    }

    /**
     * @return array{summary?: string, sources?: list<array<string, mixed>>}
     */
    private function parseJsonAnswer(string $answer): array
    {
        $trimmed = trim($answer);
        if ($trimmed === '') {
            return [];
        }

        // Strip markdown fences if the model wrapped JSON.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $m)) {
            $trimmed = $m[1];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try to find a JSON object inside the text.
        if (preg_match('/\{.*\}/s', $trimmed, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $modelSources
     * @param  list<array{title: string, url: string, excerpt: string}>  $apiSources
     * @param  list<string>  $domains
     * @return list<array{
     *   marker: string,
     *   document_id: int|null,
     *   document: string,
     *   section: string,
     *   page: int,
     *   pages: string,
     *   excerpt: string,
     *   label: string,
     *   url: string
     * }>
     */
    private function normalizeFootnotes(array $modelSources, array $apiSources, array $domains): array
    {
        $labels = config('services.chat_web_verify.outlet_labels', []);
        $out = [];
        $seen = [];
        $i = 1;

        foreach ($modelSources as $row) {
            if ($i > 6) {
                break;
            }
            $url = $this->cleanUrl((string) ($row['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            if (! $this->urlAllowed($url, $domains)) {
                continue;
            }
            $seen[$url] = true;
            $outlet = trim((string) ($row['outlet'] ?? '')) ?: $this->outletForUrl($url, $labels);
            $outlet = $this->normalizeOutletLabel($outlet, $url, $labels);
            $title = trim((string) ($row['title'] ?? '')) ?: $outlet;
            $marker = 'W'.$i;

            $out[] = [
                'marker' => $marker,
                'document_id' => null,
                'document' => $outlet,
                'section' => $title,
                'page' => 0,
                'pages' => '',
                'excerpt' => Str::limit(trim((string) ($row['excerpt'] ?? '')), 280),
                'label' => $outlet,
                'url' => $url,
            ];
            $i++;
        }

        // Fill from API annotations if the model omitted structured sources.
        foreach ($apiSources as $row) {
            if ($i > 6) {
                break;
            }
            $url = $this->cleanUrl((string) ($row['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            if (! $this->urlAllowed($url, $domains)) {
                continue;
            }
            $seen[$url] = true;
            $outlet = $this->outletForUrl($url, $labels);
            $outlet = $this->normalizeOutletLabel($outlet, $url, $labels);
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '' || $title === $url) {
                $title = $outlet.' report';
            }

            $out[] = [
                'marker' => 'W'.$i,
                'document_id' => null,
                'document' => $outlet,
                'section' => $title,
                'page' => 0,
                'pages' => '',
                'excerpt' => Str::limit(trim((string) ($row['excerpt'] ?? '')), 280),
                'label' => $outlet,
                'url' => $url,
            ];
            $i++;
        }

        return $out;
    }

    /**
     * @param  list<array{marker: string}>  $footnotes
     */
    private function ensureWebMarkers(string $summary, array $footnotes): string
    {
        foreach ($footnotes as $fn) {
            $marker = (string) ($fn['marker'] ?? '');
            if ($marker === '') {
                continue;
            }
            if (! str_contains($summary, '['.$marker.']')) {
                $summary = rtrim($summary).' ['.$marker.']';
            }
        }

        return $summary;
    }

    private function cleanUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Strip OpenAI tracking query if present but keep the article URL usable.
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $clean = $parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '');
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $q);
            unset($q['utm_source']);
            if ($q !== []) {
                $clean .= '?'.http_build_query($q);
            }
        }

        return $clean;
    }

    /**
     * @param  list<string>  $domains
     */
    private function urlAllowed(string $url, array $domains): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        foreach ($domains as $domain) {
            $domain = preg_replace('/^www\./', '', strtolower($domain)) ?? $domain;
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function normalizeOutletLabel(string $outlet, string $url, array $labels): string
    {
        $fromUrl = $this->outletForUrl($url, $labels);
        if ($fromUrl !== 'News') {
            return $fromUrl;
        }

        $lower = strtolower($outlet);
        if (str_contains($lower, 'joy')) {
            return 'JoyNews';
        }
        if (str_contains($lower, 'citi')) {
            return 'CitiFM';
        }
        if (str_contains($lower, 'gbc')) {
            return 'GBC';
        }
        if (str_contains($lower, 'gtv')) {
            return 'GTV';
        }
        if (str_contains($lower, 'tv3') || str_contains($lower, '3news') || str_contains($lower, 'three')) {
            return 'TV3';
        }
        if (str_contains($lower, 'bbc')) {
            return 'BBC';
        }
        if (str_contains($lower, 'cnn')) {
            return 'CNN';
        }
        if (str_contains($lower, 'dw') || str_contains($lower, 'deutsche')) {
            return 'DW';
        }

        return $outlet !== '' ? $outlet : 'News';
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function outletForUrl(string $url, array $labels): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        foreach ($labels as $domain => $label) {
            $domain = preg_replace('/^www\./', '', strtolower((string) $domain)) ?? $domain;
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $label;
            }
        }

        return 'News';
    }
}
