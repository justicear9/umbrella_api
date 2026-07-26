<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * OpenAI gpt-4o-mini-tts fallback with Ghanaian accent instructions
 * when Gemini TTS is rate-limited.
 */
class OpenAITtsService
{
    /** @var array<string, array{openai: string, instructions: string, label: string}> */
    public const VOICE_PRESETS = [
        GeminiTtsService::VOICE_GHANAIAN => [
            'openai' => 'ash',
            'instructions' => 'Speak in a natural Ghanaian English accent as heard in Accra. Clear, firm TV interviewer tone. Not American, not British RP.',
            'label' => 'Ghanaian (Male)',
        ],
        GeminiTtsService::VOICE_GHANAIAN_LADY => [
            'openai' => 'coral',
            'instructions' => 'Speak in a natural Ghanaian English accent as heard in Accra. Warm but sharp female interviewer. Not American, not British RP.',
            'label' => 'Ghanaian (Lady)',
        ],
        GeminiTtsService::VOICE_MALE => [
            'openai' => 'onyx',
            'instructions' => 'Calm, informative male news readout.',
            'label' => 'Male',
        ],
        GeminiTtsService::VOICE_FEMALE => [
            'openai' => 'nova',
            'instructions' => 'Clear, firm female news voice.',
            'label' => 'Female',
        ],
    ];

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{url: string, path: string, cached: bool}|null
     */
    public function synthesizeToCachedMp3(string $cacheKey, string $text, string $voicePreset): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 4000).'…';
        }

        $preset = self::VOICE_PRESETS[$voicePreset] ?? self::VOICE_PRESETS[GeminiTtsService::VOICE_GHANAIAN];
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cacheKey.'_oai_v1_'.$preset['openai']) ?: uniqid('oai_', true);
        $relativePath = 'tts/'.$safeKey.'.mp3';
        $disk = Storage::disk('public');

        if ($disk->exists($relativePath) && $disk->size($relativePath) > 100) {
            return [
                'url' => $this->publicUrl($relativePath),
                'cached' => true,
                'path' => $relativePath,
            ];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->withHeaders(['Accept' => 'audio/mpeg'])
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => config('services.openai.tts_model', 'gpt-4o-mini-tts'),
                'voice' => $preset['openai'],
                'input' => $text,
                'instructions' => $preset['instructions'],
                'response_format' => 'mp3',
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI TTS failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 400),
            ]);

            return null;
        }

        $disk->makeDirectory('tts');
        $disk->put($relativePath, $response->body());

        return [
            'url' => $this->publicUrl($relativePath),
            'cached' => false,
            'path' => $relativePath,
        ];
    }

    private function publicUrl(string $relativePath): string
    {
        $request = request();
        $base = $request?->getSchemeAndHttpHost();

        if (! $base || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $forwarded = $request?->headers->get('X-Forwarded-Host')
                ?: $request?->headers->get('Origin');
            if (is_string($forwarded) && $forwarded !== '') {
                $origin = preg_replace('#^https?://#', '', explode(',', $forwarded)[0]);
                $origin = rtrim((string) $origin, '/');
                if ($origin !== '' && ! str_contains($origin, 'localhost') && ! str_contains($origin, '127.0.0.1')) {
                    $scheme = $request?->getScheme() ?: 'http';
                    $base = $scheme.'://'.$origin;
                }
            }
        }

        if (! $base) {
            $base = rtrim((string) config('app.url'), '/');
        }

        $file = basename($relativePath);

        return rtrim($base, '/').'/api/media/tts/'.rawurlencode($file);
    }
}
