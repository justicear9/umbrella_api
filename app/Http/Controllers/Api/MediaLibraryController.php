<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\AudienceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaLibraryController extends Controller
{
    public function index(Request $request, AudienceResolver $audience)
    {
        $user = $request->user();
        $kind = $request->query('kind');

        $assets = MediaAsset::query()
            ->where('status', 'published')
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->with('targets')
            ->latest('published_at')
            ->limit(100)
            ->get()
            ->filter(function (MediaAsset $asset) use ($audience, $user) {
                return $audience->userMatches($user, $asset->audience_mode, $asset->targetIds());
            })
            ->values();

        return response()->json([
            'success' => true,
            'media' => $assets->map(fn (MediaAsset $a) => $this->serialize($a))->all(),
        ]);
    }

    public function show(Request $request, MediaAsset $mediaAsset, AudienceResolver $audience)
    {
        $media = $mediaAsset;
        $media->load('targets');
        if ($media->status !== 'published' || ! $audience->userMatches($request->user(), $media->audience_mode, $media->targetIds())) {
            abort(404);
        }

        return response()->json(['success' => true, 'media' => $this->serialize($media)]);
    }

    public function signedUrl(Request $request, MediaAsset $mediaAsset, AudienceResolver $audience)
    {
        $media = $mediaAsset;
        $media->load('targets');
        if ($media->status !== 'published' || ! $audience->userMatches($request->user(), $media->audience_mode, $media->targetIds())) {
            abort(404);
        }

        $expires = time() + 900;
        $sig = $this->sign($media->id, $expires);
        $path = "/api/media-assets/{$media->id}/file";
        $query = ['expires' => $expires, 'sig' => $sig];

        return response()->json([
            'success' => true,
            'url' => $path.'?'.http_build_query($query),
            'title' => $media->title,
            'expires_at' => $expires,
        ]);
    }

    public function file(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        $media = $mediaAsset;
        $expires = (int) $request->query('expires', 0);
        $sig = (string) $request->query('sig', '');
        if ($expires < time() || ! hash_equals($this->sign($media->id, $expires), $sig)) {
            abort(403, 'Invalid or expired link');
        }

        if ($media->status !== 'published' || ! $media->file_path || ! Storage::disk('local')->exists($media->file_path)) {
            abort(404, 'File missing');
        }

        $media->increment('download_count');
        $absolute = Storage::disk('local')->path($media->file_path);
        $filename = basename($media->original_filename ?: $media->file_path);

        return response()->stream(function () use ($absolute) {
            $stream = fopen($absolute, 'rb');
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) filesize($absolute),
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function serialize(MediaAsset $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'description' => $a->description,
            'kind' => $a->kind,
            'mime' => $a->mime,
            'byte_size' => $a->byte_size,
            'original_filename' => $a->original_filename,
            'published_at' => $a->published_at?->toIso8601String(),
        ];
    }

    private function sign(int $mediaId, int $expires): string
    {
        return hash_hmac('sha256', $mediaId.'|'.$expires, (string) config('app.key'));
    }
}
