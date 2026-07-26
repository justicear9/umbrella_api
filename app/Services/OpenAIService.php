<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAIService
{
    private string $apiKey;
    private string $model;
    private string $embeddingModel;
    private float $temperature;
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.api_key');
        $this->model = (string) config('services.openai.model', 'gpt-4o-mini');
        $this->embeddingModel = (string) config('services.openai.embedding_model', 'text-embedding-3-small');
        $this->temperature = (float) config('services.openai.temperature', 0.3);
        $this->maxTokens = (int) config('services.openai.max_tokens', 2000);
    }

    public function chat(array $messages, array $options = []): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
        ];

        if (! empty($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(180)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            Log::error('OpenAI chat failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('OpenAI chat request failed: '.$response->status());
        }

        return trim((string) data_get($response->json(), 'choices.0.message.content', ''));
    }

    public function chatJson(array $messages, array $options = []): array
    {
        $options['response_format'] = ['type' => 'json_object'];
        $content = $this->chat($messages, $options);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * Responses API with hosted web_search (domain-filtered when the model supports it).
     *
     * @param  list<string>  $allowedDomains
     * @return array{answer: string, sources: list<array{title: string, url: string, excerpt: string}>}
     */
    public function responsesWithWebSearch(string $input, array $allowedDomains = [], array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $model = (string) ($options['model'] ?? config('services.openai.web_search_model', 'gpt-4o'));
        $domains = array_values(array_unique(array_filter(array_map(
            static fn ($d) => strtolower(trim((string) $d)),
            $allowedDomains
        ))));

        $tool = ['type' => 'web_search'];
        if ($domains !== []) {
            $tool['filters'] = ['allowed_domains' => $domains];
        }

        $payload = [
            'model' => $model,
            'tools' => [$tool],
            'tool_choice' => $options['tool_choice'] ?? 'auto',
            'include' => ['web_search_call.action.sources'],
            'temperature' => $options['temperature'] ?? 0.2,
            'input' => $input,
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(180)
            ->post('https://api.openai.com/v1/responses', $payload);

        if (! $response->successful()) {
            Log::error('OpenAI responses web_search failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('OpenAI web search request failed: '.$response->status());
        }

        $json = $response->json();
        $answer = '';
        $sources = [];
        $seen = [];

        foreach ($json['output'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'message') {
                foreach ($item['content'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'output_text') {
                        $answer .= (string) ($block['text'] ?? '');
                        foreach ($block['annotations'] ?? [] as $ann) {
                            if (($ann['type'] ?? '') !== 'url_citation') {
                                continue;
                            }
                            $url = trim((string) ($ann['url'] ?? ''));
                            if ($url === '' || isset($seen[$url])) {
                                continue;
                            }
                            if ($domains !== [] && ! $this->urlMatchesAllowedDomains($url, $domains)) {
                                continue;
                            }
                            $seen[$url] = true;
                            $sources[] = [
                                'title' => trim((string) ($ann['title'] ?? '')) ?: $url,
                                'url' => $url,
                                'excerpt' => '',
                            ];
                        }
                    }
                }
            }

            if (($item['type'] ?? '') === 'web_search_call') {
                foreach ($item['action']['sources'] ?? [] as $src) {
                    $url = trim((string) ($src['url'] ?? ''));
                    if ($url === '' || isset($seen[$url])) {
                        continue;
                    }
                    if ($domains !== [] && ! $this->urlMatchesAllowedDomains($url, $domains)) {
                        continue;
                    }
                    $seen[$url] = true;
                    $sources[] = [
                        'title' => $url,
                        'url' => $url,
                        'excerpt' => '',
                    ];
                }
            }
        }

        return [
            'answer' => trim($answer),
            'sources' => array_slice($sources, 0, 8),
        ];
    }

    /**
     * @param  list<string>  $allowedDomains
     */
    private function urlMatchesAllowedDomains(string $url, array $allowedDomains): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        foreach ($allowedDomains as $domain) {
            $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $texts = array_values(array_filter(array_map('trim', $texts)));
        if ($texts === []) {
            return [];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->embeddingModel,
                'input' => $texts,
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI embeddings failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('OpenAI embeddings request failed: '.$response->status());
        }

        $data = collect($response->json('data', []))
            ->sortBy('index')
            ->pluck('embedding')
            ->values()
            ->all();

        return $data;
    }
}
