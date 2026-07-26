<?php

namespace App\Services;

use App\Models\Briefing;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentIngestService
{
    public function __construct(private OpenAIService $openai) {}

    public function process(Document $document): void
    {
        $document->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $absolutePath = Storage::disk('local')->path($document->file_path);
            $pages = $this->extractPages($absolutePath);
            $chunks = $this->chunkPages($pages);

            $document->chunks()->delete();
            $document->briefings()->delete();

            $this->storeChunks($document, $chunks);
            $this->generateBriefings($document, $chunks);

            $document->update([
                'status' => 'ready',
                'page_count' => count($pages),
                'chunk_count' => count($chunks),
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Document ingest failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<int, string> pageNumber => text
     */
    private function extractPages(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new \RuntimeException('Document file not found on disk.');
        }

        $result = Process::timeout(300)->run([
            'pdftotext',
            '-layout',
            $absolutePath,
            '-',
        ]);

        if ($result->successful() && trim($result->output()) !== '') {
            return $this->splitPlainTextIntoPages($result->output());
        }

        $parser = new \Smalot\PdfParser\Parser;
        $pdf = $parser->parseFile($absolutePath);
        $pages = [];
        foreach ($pdf->getPages() as $index => $page) {
            $text = trim($page->getText());
            if ($text !== '') {
                $pages[$index + 1] = $text;
            }
        }

        if ($pages === []) {
            throw new \RuntimeException('Could not extract text from PDF.');
        }

        return $pages;
    }

    /**
     * @return array<int, string>
     */
    private function splitPlainTextIntoPages(string $text): array
    {
        $parts = preg_split("/\f+/", $text) ?: [$text];
        $pages = [];
        foreach ($parts as $i => $part) {
            $clean = trim(preg_replace("/[ \t]+/u", ' ', $part) ?? $part);
            if ($clean !== '') {
                $pages[$i + 1] = $clean;
            }
        }

        return $pages !== [] ? $pages : [1 => trim($text)];
    }

    /**
     * @param  array<int, string>  $pages
     * @return list<array{content: string, page_start: int, page_end: int}>
     */
    private function chunkPages(array $pages): array
    {
        $chunks = [];
        $buffer = '';
        $pageStart = null;
        $pageEnd = null;
        $targetChars = 3500;

        foreach ($pages as $pageNum => $text) {
            $pageStart ??= $pageNum;
            $pageEnd = $pageNum;
            $candidate = $buffer === '' ? $text : $buffer."\n\n".$text;

            if (mb_strlen($candidate) >= $targetChars && $buffer !== '') {
                $chunks[] = [
                    'content' => $buffer,
                    'page_start' => $pageStart,
                    'page_end' => $pageEnd - 1,
                ];
                $buffer = $text;
                $pageStart = $pageNum;
                $pageEnd = $pageNum;
            } else {
                $buffer = $candidate;
            }
        }

        if (trim($buffer) !== '') {
            $chunks[] = [
                'content' => $buffer,
                'page_start' => $pageStart ?? 1,
                'page_end' => $pageEnd ?? ($pageStart ?? 1),
            ];
        }

        return $chunks;
    }

    /**
     * @param  list<array{content: string, page_start: int, page_end: int}>  $chunks
     */
    private function storeChunks(Document $document, array $chunks): void
    {
        foreach (array_chunk($chunks, 16) as $batchIndex => $batch) {
            $embeddings = $this->openai->embed(array_column($batch, 'content'));

            foreach ($batch as $i => $chunk) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'chunk_index' => ($batchIndex * 16) + $i,
                    'page_start' => $chunk['page_start'],
                    'page_end' => $chunk['page_end'],
                    'content' => $chunk['content'],
                    'embedding' => $embeddings[$i] ?? null,
                ]);
            }
        }
    }

    /**
     * Re-digest briefing cards from already-stored chunks (no re-embed).
     */
    public function regenerateBriefings(Document $document): void
    {
        $chunks = $document->chunks()
            ->orderBy('chunk_index')
            ->get()
            ->map(fn (DocumentChunk $c) => [
                'content' => $c->content,
                'page_start' => (int) $c->page_start,
                'page_end' => (int) $c->page_end,
            ])
            ->all();

        if ($chunks === []) {
            throw new \RuntimeException('Document has no chunks to digest.');
        }

        $document->briefings()->delete();
        $this->generateBriefings($document, $chunks);
    }

    /**
     * @param  list<array{content: string, page_start: int, page_end: int}>  $chunks
     */
    private function generateBriefings(Document $document, array $chunks): void
    {
        $categories = collect(config('ndc.categories'))
            ->filter(fn ($c) => ($c['query'] ?? null) !== null)
            ->pluck('query')
            ->values()
            ->all();

        $created = [];
        foreach ($categories as $category) {
            $sourceText = $this->sourceTextForCategory($category, $chunks);
            $row = $this->briefingForCategory($document, $category, $sourceText);
            if ($row === null) {
                continue;
            }

            Briefing::create([
                'document_id' => $document->id,
                'title' => (string) ($row['title'] ?? $category),
                'category' => $category,
                'summary' => (string) ($row['summary'] ?? ''),
                'talking_points' => array_values($row['talking_points'] ?? []),
                'key_stats' => array_values($row['key_stats'] ?? []),
                'watch_outs' => array_values($row['watch_outs'] ?? []),
                'citations' => array_values($row['citations'] ?? []),
                'published_at' => now(),
            ]);
            $created[] = $category;
        }

        $missing = array_values(array_diff($categories, $created));
        if ($missing !== []) {
            Log::warning('Briefing digest incomplete', [
                'document_id' => $document->id,
                'missing' => $missing,
            ]);
        }
    }

    /**
     * @param  list<array{content: string, page_start: int, page_end: int}>  $chunks
     */
    private function sourceTextForCategory(string $category, array $chunks): string
    {
        $keywords = $this->keywordsForCategory($category);
        $scored = [];

        foreach ($chunks as $index => $chunk) {
            $hay = mb_strtolower($chunk['content']);
            $score = 0;
            foreach ($keywords as $kw) {
                $score += substr_count($hay, mb_strtolower($kw));
            }
            // Light preference for middle/later fiscal chapters for sectoral categories
            if ($score > 0) {
                $scored[] = ['score' => $score, 'index' => $index, 'chunk' => $chunk];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index']);

        $picked = array_slice($scored, 0, 10);
        if (count($picked) < 6) {
            // Fall back to a broad sample so every category still gets a card
            $fallback = array_merge(
                array_slice($chunks, 0, 8),
                array_slice($chunks, max(0, (int) floor(count($chunks) / 2) - 4), 8),
                array_slice($chunks, max(0, count($chunks) - 8))
            );
            $seen = [];
            foreach ($fallback as $chunk) {
                $key = $chunk['page_start'].'-'.$chunk['page_end'].'-'.mb_substr($chunk['content'], 0, 40);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $picked[] = ['score' => 0, 'index' => 0, 'chunk' => $chunk];
                if (count($picked) >= 12) {
                    break;
                }
            }
        }

        return mb_substr(
            collect($picked)
                ->map(fn ($p) => "Pages {$p['chunk']['page_start']}-{$p['chunk']['page_end']}:\n".mb_substr($p['chunk']['content'], 0, 1600))
                ->implode("\n\n---\n\n"),
            0,
            36000
        );
    }

    /**
     * @return list<string>
     */
    private function keywordsForCategory(string $category): array
    {
        return match ($category) {
            'Economy & Fiscal' => ['fiscal', 'budget', 'revenue', 'gdp', 'inflation', 'growth', 'tax', 'expenditure'],
            'Jobs & 24-Hour Economy' => ['job', 'employment', 'unemployment', 'labour', 'labor', '24-hour', 'sme', 'youth'],
            'Infrastructure' => ['infrastructure', 'road', 'rail', 'port', 'airport', 'housing', 'water', 'bridge'],
            'Energy & Oil/Gas' => ['energy', 'electricity', 'power', 'oil', 'gas', 'ecg', 'petroleum', 'fuel'],
            'Health' => ['health', 'hospital', 'nhis', 'medical', 'pharma', 'clinic', 'disease'],
            'Education' => ['education', 'school', 'teacher', 'student', 'university', 'free shs', 'tvets', 'tvet'],
            'Debt & IMF' => ['debt', 'imf', 'bond', 'restructuring', 'arrears', 'creditor', 'eurobond'],
            'Governance & Reforms' => ['governance', 'reform', 'corruption', 'procurement', 'accountability', 'transparency', 'audit'],
            default => explode(' ', mb_strtolower($category)),
        };
    }

    /**
     * @return array{title?: string, summary?: string, talking_points?: list<string>, key_stats?: list<string>, watch_outs?: list<string>, citations?: list<string>}|null
     */
    private function briefingForCategory(Document $document, string $category, string $sourceText): ?array
    {
        $system = <<<'PROMPT'
You are a senior NDC (National Democratic Congress, Ghana) communications strategist.
Create ONE briefing card for the given category from the Mid-Year / fiscal policy source excerpts.
Return STRICT JSON with this shape:
{
  "title": "short punchy headline",
  "summary": "2-4 sentence communicator summary",
  "talking_points": ["point1", "point2", "point3"],
  "key_stats": ["stat or figure with context"],
  "watch_outs": ["opponent trap or hostile framing to prepare for"],
  "citations": ["Pages X-Y: short quote or fact"]
}
Rules:
- Only use facts present in the source text. Do not invent numbers.
- You MUST produce a briefing for this category. Prefer sector-specific budget, spending, policy, or reform references.
- If the category is thin in the excerpts, still write a careful briefing from the closest related fiscal/budget material — never skip the category.
- Talking points must be speakable on radio/TV (short, confident, Ghana-context).
- watch_outs should anticipate NPP / opposition / media attacks.
PROMPT;

        $user = "Category (exact): {$category}\nDocument title: {$document->title}\n\nSOURCE TEXT:\n".$sourceText;

        try {
            $result = $this->openai->chatJson([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], ['max_tokens' => 1200, 'temperature' => 0.2]);
        } catch (Throwable $e) {
            Log::error('Category briefing failed', [
                'category' => $category,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_array($result) || trim((string) ($result['summary'] ?? '')) === '') {
            return null;
        }

        return $result;
    }
}
