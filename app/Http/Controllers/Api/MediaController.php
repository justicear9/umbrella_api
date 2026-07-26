<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PressPrepTtsService;
use App\Services\WhisperService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
     * Stream a cached TTS file with a phone-friendly Content-Type.
     * Avoids brittle /storage symlink + audio/wave MIME issues in Expo AV.
     */
    public function audio(string $file): BinaryFileResponse
    {
        $file = basename($file);
        if (! preg_match('/^[a-zA-Z0-9_-]+\.(wav|mp3|m4a)$/', $file)) {
            abort(404);
        }

        $path = storage_path('app/public/tts/'.$file);
        if (! is_file($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            default => 'audio/wav',
        };

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function tts(Request $request, PressPrepTtsService $tts)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'voice' => ['nullable', 'string'],
            'cache_key' => ['nullable', 'string', 'max:120'],
        ]);

        $voice = $data['voice'] ?? 'ghanaian';
        $cacheKey = $data['cache_key'] ?? ('tts_'.md5($data['text'].'|'.$voice));

        $result = $tts->speak($cacheKey, $data['text'], $voice);
        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Could not synthesize speech (Gemini quota and OpenAI fallback both failed).',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'audio_url' => $result['url'],
            'cached' => $result['cached'],
            'engine' => $result['engine'],
            'voice' => $voice,
        ]);
    }

    public function stt(Request $request, WhisperService $whisper)
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:25600'],
        ]);

        $text = $whisper->transcribe($request->file('audio'));

        return response()->json([
            'success' => true,
            'text' => $text,
        ]);
    }
}
