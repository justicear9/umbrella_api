<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function signedUrl(Request $request, Document $document)
    {
        if ($document->status !== 'ready') {
            abort(404, 'Document not ready');
        }

        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = (int) ($data['page'] ?? 1);
        if ($document->page_count && $page > (int) $document->page_count) {
            $page = (int) $document->page_count;
        }

        $expires = time() + 900;
        $sig = $this->sign($document->id, $page, $expires);
        $path = "/api/documents/{$document->id}/file";
        $query = [
            'page' => $page,
            'expires' => $expires,
            'sig' => $sig,
        ];

        // Relative path so the mobile client can prefix its configured API base URL
        // (LAN IP / cPanel), instead of APP_URL localhost.
        return response()->json([
            'success' => true,
            'path' => $path,
            'query' => $query,
            'url' => $path.'?'.http_build_query($query),
            'page' => $page,
            'title' => $document->title,
            'file_ready' => $this->resolveAbsolutePath($document) !== null,
            'file_path' => $document->file_path,
            'expires_at' => $expires,
        ]);
    }

    public function file(Request $request, Document $document): StreamedResponse
    {
        $page = (int) $request->query('page', 1);
        $expires = (int) $request->query('expires', 0);
        $sig = (string) $request->query('sig', '');

        if ($expires < time() || ! hash_equals($this->sign($document->id, $page, $expires), $sig)) {
            abort(403, 'Invalid or expired link');
        }

        if ($document->status !== 'ready' || ! $document->file_path) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $absolute = $this->resolveAbsolutePath($document);
        if ($absolute === null) {
            abort(404, 'File missing at '.$document->file_path.' (upload the PDF to storage/app/private/'.$document->file_path.' on the server)');
        }

        $filename = basename($document->original_filename ?: $document->file_path);

        return response()->stream(function () use ($absolute) {
            $stream = fopen($absolute, 'rb');
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) filesize($absolute),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=600',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Range',
            'X-Document-Page' => (string) max(1, $page),
        ]);
    }

    /**
     * Resolve a document PDF on disk. Laravel's local disk roots at storage/app/private,
     * but some deploys also keep files under storage/app/documents.
     */
    private function resolveAbsolutePath(Document $document): ?string
    {
        $relative = (string) $document->file_path;
        if ($relative === '') {
            return null;
        }

        $candidates = [
            Storage::disk('local')->path($relative),
            storage_path('app/private/'.$relative),
            storage_path('app/'.$relative),
        ];

        foreach (array_unique($candidates) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function replaceFile(Request $request, Document $document)
    {
        $adminKey = (string) $request->header('X-Admin-Key', '');
        $expected = (string) config('ndc.admin_key', env('NDC_ADMIN_KEY'));
        if ($expected === '' || ! hash_equals($expected, $adminKey)) {
            abort(403, 'Forbidden');
        }

        if ($document->status !== 'ready' && $document->status !== 'pending') {
            abort(404, 'Document not found');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $file = $data['file'];
        $relative = $document->file_path ?: ('documents/'.basename((string) $file->hashName('documents')));
        if (! str_contains($relative, '/')) {
            $relative = 'documents/'.$relative;
        }

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->putFileAs(dirname($relative), $file, basename($relative));

        $document->update([
            'file_path' => $relative,
            'original_filename' => $file->getClientOriginalName() ?: $document->original_filename,
        ]);

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'title' => $document->title,
            'file_path' => $document->file_path,
            'bytes' => Storage::disk('local')->size($document->file_path),
        ]);
    }

    private function sign(int $documentId, int $page, int $expires): string
    {
        return hash_hmac('sha256', $documentId.'|'.$page.'|'.$expires, (string) config('app.key'));
    }
}
