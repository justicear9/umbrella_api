<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhisperService
{
    public function transcribe(UploadedFile $file): string
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(300)
            ->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName() ?: 'audio.m4a'
            )
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'en',
            ]);

        if (! $response->successful()) {
            Log::error('Whisper failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Could not transcribe audio.');
        }

        return trim((string) $response->json('text', ''));
    }
}
