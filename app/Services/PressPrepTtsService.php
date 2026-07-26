<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * MMRevamp-parity Ghanaian voices: Gemini Achird / Sulafat only.
 * Live interview uses a fast path (few/no quota waits).
 */
class PressPrepTtsService
{
    public function __construct(
        private GeminiTtsService $gemini,
        private OpenAITtsService $openai,
    ) {}

    /**
     * @return array{url: string, engine: string, cached: bool}|null
     */
    public function speak(string $cacheKey, string $text, string $voicePreset, bool $fast = true): ?array
    {
        $voicePreset = $this->gemini->normalizeVoicePreset($voicePreset);
        $isGhanaian = in_array($voicePreset, [
            GeminiTtsService::VOICE_GHANAIAN,
            GeminiTtsService::VOICE_GHANAIAN_LADY,
        ], true);

        // Shorter scripts = faster Gemini synthesis.
        $text = $this->trimForSpeech($text);

        if ($this->gemini->isConfigured()) {
            // Live path: try once (retry once only on 429 with a short wait).
            $attempts = $fast ? 2 : ($isGhanaian ? 5 : 2);
            for ($i = 0; $i < $attempts; $i++) {
                $result = $this->gemini->synthesizeToCachedWav($cacheKey, $text, $voicePreset);
                if ($result) {
                    return [
                        'url' => $result['url'],
                        'engine' => 'gemini',
                        'cached' => (bool) ($result['cached'] ?? false),
                    ];
                }

                if ($i >= $attempts - 1) {
                    break;
                }

                if ($fast) {
                    $wait = min(2.0, $this->gemini->lastRetryAfterSeconds() ?? 1.2);
                    if (($this->gemini->lastHttpStatus() ?? 0) !== 429) {
                        break; // don't burn time on non-quota failures
                    }
                } else {
                    $wait = $this->gemini->lastRetryAfterSeconds() ?? (8 + ($i * 6));
                    $wait = max(3.0, min(45.0, $wait + 0.5));
                }

                Log::info('Waiting for Gemini TTS quota', [
                    'attempt' => $i + 1,
                    'wait' => $wait,
                    'fast' => $fast,
                    'status' => $this->gemini->lastHttpStatus(),
                ]);
                usleep((int) round($wait * 1_000_000));
            }
        }

        // Never substitute non-Ghanaian voices for Ghanaian presets
        if ($isGhanaian) {
            Log::error('Gemini Ghanaian TTS unavailable after retries');

            return null;
        }

        $openai = $this->openai->synthesizeToCachedMp3($cacheKey, $text, $voicePreset);
        if ($openai) {
            return [
                'url' => $openai['url'],
                'engine' => 'openai',
                'cached' => (bool) ($openai['cached'] ?? false),
            ];
        }

        return null;
    }

    /** Prefer the actual question (after any interviewer beat) and keep it short. */
    private function trimForSpeech(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        // Always speak only the question body when a beat + blank line precede it,
        // so every turn has the same "one interviewer line" shape (more stable timbre).
        if (preg_match('/\n\s*\n(.+)$/s', $text, $m)) {
            $tail = trim($m[1]);
            if ($tail !== '') {
                $text = $tail;
            }
        }

        // Strip markdown / stage directions that tempt Gemini to "perform".
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[*_`#]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > 420) {
            $cut = mb_substr($text, 0, 420);
            $cut = preg_replace('/\s+\S*$/u', '', $cut) ?: $cut;
            $text = rtrim($cut, " \t.,;:").'.';
        }

        return $text;
    }
}
