<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IngestMidYearReview extends Command
{
    protected $signature = 'ndc:ingest-midyear {--path=}';

    protected $description = 'Ingest the 2026 Mid-Year Fiscal Policy Review PDF into briefings';

    public function handle(DocumentIngestService $ingest): int
    {
        $source = $this->option('path')
            ?: base_path('../2026-Mid-Year-Fiscal-Policy-Review.pdf.pdf');

        if (! is_file($source)) {
            $this->error("PDF not found at {$source}");

            return self::FAILURE;
        }

        $this->info('Copying PDF into storage...');
        Storage::disk('local')->makeDirectory('documents');
        $filename = Str::uuid().'.pdf';
        $dest = Storage::disk('local')->path('documents/'.$filename);
        File::copy($source, $dest);

        $document = Document::create([
            'title' => '2026 Mid-Year Fiscal Policy Review',
            'original_filename' => basename($source),
            'file_path' => 'documents/'.$filename,
            'status' => 'pending',
        ]);

        $this->info("Document #{$document->id} created. Extracting, embedding, and digesting (this may take several minutes)...");
        set_time_limit(0);

        $ingest->process($document->fresh());

        $document->refresh();
        $this->info("Done. Status={$document->status}, pages={$document->page_count}, chunks={$document->chunk_count}, briefings=".$document->briefings()->count());

        if ($document->status === 'failed') {
            $this->error($document->error_message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
