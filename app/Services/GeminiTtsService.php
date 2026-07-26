<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiTtsService
{
    public const VOICE_FEMALE = 'female';

    public const VOICE_MALE = 'male';

    public const VOICE_GHANAIAN = 'ghanaian';

    public const VOICE_GHANAIAN_LADY = 'ghanaian_lady';

    /** @var array<string, array{gemini: string, label: string}> */
    public const VOICE_PRESETS = [
        self::VOICE_FEMALE => [
            'gemini' => 'Kore',
            'label' => 'Female',
        ],
        self::VOICE_MALE => [
            'gemini' => 'Charon',
            'label' => 'Male',
        ],
        self::VOICE_GHANAIAN => [
            'gemini' => 'Achird',
            'label' => 'Ghanaian (Male)',
        ],
        self::VOICE_GHANAIAN_LADY => [
            'gemini' => 'Sulafat',
            'label' => 'Ghanaian (Lady)',
        ],
    ];

    /** Bumped when prompt / sample-rate locking changes (keeps interviewer timbre stable). */
    private const CACHE_VERSION = 'v5stable';

    /** Gemini Flash TTS returns 24 kHz PCM16 — wrong rate = audible pitch drift. */
    private const OUTPUT_SAMPLE_RATE = 24000;

    private string $apiKey;

    private string $model;

    private string $defaultVoicePreset;

    private ?int $lastHttpStatus = null;

    private ?float $lastRetryAfterSeconds = null;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.tts_model', 'gemini-2.5-flash-preview-tts');
        $configured = strtolower((string) config('services.gemini.tts_voice_preset', self::VOICE_GHANAIAN));
        $this->defaultVoicePreset = array_key_exists($configured, self::VOICE_PRESETS)
            ? $configured
            : self::VOICE_GHANAIAN;
    }

    public function lastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    public function lastRetryAfterSeconds(): ?float
    {
        return $this->lastRetryAfterSeconds;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function normalizeVoicePreset(?string $voice): string
    {
        $voice = strtolower(trim((string) $voice));
        if (array_key_exists($voice, self::VOICE_PRESETS)) {
            return $voice;
        }

        return $this->defaultVoicePreset;
    }

    public function voiceLabel(string $preset): string
    {
        $preset = $this->normalizeVoicePreset($preset);

        return self::VOICE_PRESETS[$preset]['label'];
    }

    /**
     * @return array{url: string, path: string, cached: bool}|null
     */
    public function synthesizeToCachedWav(string $cacheKey, string $text, string $voicePreset = self::VOICE_GHANAIAN): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $voicePreset = $this->normalizeVoicePreset($voicePreset);
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > 4200) {
            $text = mb_substr($text, 0, 4200).'…';
        }

        $prompt = $this->buildPrompt($text, $voicePreset);
        $geminiVoice = self::VOICE_PRESETS[$voicePreset]['gemini'];
        $versionedKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cacheKey.'_'.self::CACHE_VERSION.'_'.strtolower($geminiVoice)) ?: uniqid('tts_', true);
        $m4aRelative = 'tts/'.$versionedKey.'.m4a';
        $wavRelative = 'tts/'.$versionedKey.'.wav';
        $disk = Storage::disk('public');

        // Prefer AAC/M4A — Expo AV on iOS is unreliable with Gemini's 24 kHz WAV over HTTP.
        if ($disk->exists($m4aRelative) && $disk->size($m4aRelative) > 100) {
            return [
                'url' => $this->publicUrl($m4aRelative),
                'cached' => true,
                'path' => $m4aRelative,
            ];
        }

        if ($disk->exists($wavRelative) && $disk->size($wavRelative) > 44) {
            $converted = $this->convertWavToM4a($disk->path($wavRelative), $disk->path($m4aRelative));
            $path = $converted ? $m4aRelative : $wavRelative;

            return [
                'url' => $this->publicUrl($path),
                'cached' => true,
                'path' => $path,
            ];
        }

        $pcm = $this->requestPcmAudio($prompt, $geminiVoice, $voicePreset);
        if ($pcm === null) {
            return null;
        }

        // Always encode at Gemini's native 24 kHz. Mime rate parsing is unreliable and
        // mis-labeling causes the interviewer to sound higher/lower between questions.
        $sampleRate = self::OUTPUT_SAMPLE_RATE;
        $detected = $this->detectSampleRate($pcm['mime']);
        if ($detected !== null && $detected !== self::OUTPUT_SAMPLE_RATE) {
            Log::info('Gemini TTS mime rate ignored for pitch stability', [
                'mime' => $pcm['mime'],
                'detected' => $detected,
                'using' => self::OUTPUT_SAMPLE_RATE,
            ]);
        }

        $disk->makeDirectory('tts');

        // Prefer direct PCM → AAC (skips writing a large WAV first).
        if ($this->pcmToM4a($pcm['data'], $sampleRate, $disk->path($m4aRelative))) {
            return [
                'url' => $this->publicUrl($m4aRelative),
                'cached' => false,
                'path' => $m4aRelative,
            ];
        }

        $wav = $this->pcmToWav($pcm['data'], $sampleRate, 1, 16);
        $disk->put($wavRelative, $wav);

        $relativePath = $wavRelative;
        if ($this->convertWavToM4a($disk->path($wavRelative), $disk->path($m4aRelative))) {
            $relativePath = $m4aRelative;
            $disk->delete($wavRelative);
        }

        return [
            'url' => $this->publicUrl($relativePath),
            'cached' => false,
            'path' => $relativePath,
        ];
    }

    private function buildPrompt(string $transcript, string $voicePreset): string
    {
        $transcript = trim(preg_replace('/\s+/u', ' ', $transcript) ?? $transcript);
        $transcript = str_replace(['"', '“', '”'], "'", $transcript);

        // Fixed director notes (same every turn) so Gemini doesn't reinvent pitch/character.
        // Prompt tone must match the chosen speaker — see Gemini TTS voice-consistency guidance.
        $style = match ($voicePreset) {
            self::VOICE_GHANAIAN_LADY => 'Read aloud as the same Ghanaian woman TV interviewer every time: warm Accra English, steady mid pitch, calm and firm. Do not raise pitch, whisper, shout, giggle, or act. Keep an even pace.',
            self::VOICE_GHANAIAN => 'Read aloud as the same Ghanaian man TV interviewer every time: Accra English, steady mid-low pitch, calm and firm. Do not raise pitch, whisper, shout, laugh, or act. Keep an even pace.',
            self::VOICE_FEMALE => 'Read aloud as the same woman news interviewer every time: clear mid pitch, calm and firm. Do not change pitch or character.',
            default => 'Read aloud as the same man news interviewer every time: clear mid-low pitch, calm and firm. Do not change pitch or character.',
        };

        return $style."\nDo not read these instructions aloud.\n\nTranscript:\n".$transcript;
    }

    /**
     * @return array{data: string, mime: string}|null
     */
    private function requestPcmAudio(string $text, string $geminiVoice, string $voicePreset): ?array
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            urlencode($this->apiKey)
        );

        // temperature left at model default — very low values can destabilize Gemini TTS.
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $geminiVoice,
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return null;
        }

        try {
            $tmpDir = storage_path('app/tmp');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0775, true);
            }
            putenv('TMPDIR='.$tmpDir);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_CONNECTTIMEOUT => 8,
            ]);

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            $this->lastHttpStatus = $status;
            $this->lastRetryAfterSeconds = null;

            if ($raw === false || $status < 200 || $status >= 300) {
                if (is_string($raw) && preg_match('/Please retry in ([0-9.]+)s/i', $raw, $m)) {
                    $this->lastRetryAfterSeconds = (float) $m[1];
                } elseif ($status === 429) {
                    $this->lastRetryAfterSeconds = 12.0;
                }

                Log::error('Gemini TTS request failed', [
                    'status' => $status,
                    'error' => $err,
                    'voice' => $geminiVoice,
                    'preset' => $voicePreset,
                    'retry_after' => $this->lastRetryAfterSeconds,
                    'body' => mb_substr(is_string($raw) ? $raw : '', 0, 800),
                ]);

                return null;
            }

            $data = json_decode($raw, true);
            $part = $data['candidates'][0]['content']['parts'][0] ?? null;
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;

            if (! $inline || empty($inline['data'])) {
                return null;
            }

            $binary = base64_decode($inline['data'], true);
            if ($binary === false || $binary === '') {
                return null;
            }

            return [
                'data' => $binary,
                'mime' => (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini TTS exception: '.$e->getMessage());

            return null;
        }
    }

    private function detectSampleRate(string $mime): ?int
    {
        if (preg_match('/rate=(\d+)/i', $mime, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function pcmToWav(string $pcmData, int $sampleRate, int $channels, int $bitsPerSample): string
    {
        $byteRate = (int) ($sampleRate * $channels * ($bitsPerSample / 8));
        $blockAlign = (int) ($channels * ($bitsPerSample / 8));
        $dataSize = strlen($pcmData);

        $header = pack(
            'A4VA4A4VvvVVvvA4V',
            'RIFF',
            36 + $dataSize,
            'WAVE',
            'fmt ',
            16,
            1,
            $channels,
            $sampleRate,
            $byteRate,
            $blockAlign,
            $bitsPerSample,
            'data',
            $dataSize
        );

        return $header.$pcmData;
    }

    private function convertWavToM4a(string $wavAbsolute, string $m4aAbsolute): bool
    {
        $ffmpeg = $this->ffmpegBinary();
        if ($ffmpeg === null) {
            Log::warning('Gemini TTS: ffmpeg not found; serving WAV (may fail on some iOS devices)');

            return false;
        }

        $dir = dirname($m4aAbsolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cmd = sprintf(
            '%s -y -i %s -c:a aac -b:a 64k -movflags +faststart %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($wavAbsolute),
            escapeshellarg($m4aAbsolute)
        );
        exec($cmd, $output, $code);

        if ($code !== 0 || ! is_file($m4aAbsolute) || filesize($m4aAbsolute) < 100) {
            Log::warning('Gemini TTS: ffmpeg m4a conversion failed', [
                'code' => $code,
                'output' => implode("\n", array_slice($output, -8)),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Encode raw PCM16LE mono straight to M4A — avoids a big intermediate WAV write.
     */
    private function pcmToM4a(string $pcmData, int $sampleRate, string $m4aAbsolute): bool
    {
        $ffmpeg = $this->ffmpegBinary();
        if ($ffmpeg === null || $pcmData === '') {
            return false;
        }

        $dir = dirname($m4aAbsolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $cmd = sprintf(
            '%s -y -f s16le -ar %d -ac 1 -i pipe:0 -c:a aac -b:a 64k -movflags +faststart %s',
            escapeshellarg($ffmpeg),
            max(8000, $sampleRate),
            escapeshellarg($m4aAbsolute)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => false]);
        if (! is_resource($proc)) {
            return false;
        }

        fwrite($pipes[0], $pcmData);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0 || ! is_file($m4aAbsolute) || filesize($m4aAbsolute) < 100) {
            Log::warning('Gemini TTS: pcm→m4a failed', [
                'code' => $code,
                'stderr' => mb_substr($stderr.$stdout, 0, 400),
            ]);

            return false;
        }

        return true;
    }

    private function ffmpegBinary(): ?string
    {
        foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg'] as $candidate) {
            if ($candidate === 'ffmpeg') {
                $which = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
                if ($which !== '' && is_executable($which)) {
                    return $which;
                }

                continue;
            }

            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function publicUrl(string $relativePath): string
    {
        // Serve via /api/media/tts/{file} so phones get correct Content-Type + byte range.
        // Mobile also rewrites the host to its configured API base as a safety net.
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
