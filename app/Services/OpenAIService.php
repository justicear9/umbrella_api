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
