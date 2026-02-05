<?php

namespace App\Listeners\DocumentGenerator;

use Ayoratoumvone\Documentgeneratorx\Events\BatchGenerationCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Example listener: Handle batch completion
 * 
 * This listener fires when all documents in a batch have been generated.
 * Use it to zip files together, send notifications, clean up, etc.
 * 
 * Register this listener in your EventServiceProvider:
 * 
 *   protected $listen = [
 *       \Ayoratoumvone\Documentgeneratorx\Events\BatchGenerationCompleted::class => [
 *           \App\Listeners\DocumentGenerator\HandleBatchCompleted::class,
 *       ],
 *   ];
 */
class HandleBatchCompleted implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(BatchGenerationCompleted $event): void
    {
        Log::info('Batch generation completed', [
            'batch_id' => $event->batchId,
            'total' => $event->totalDocuments,
            'successful' => $event->successCount,
            'failed' => $event->failedCount,
            'success_rate' => $event->getSuccessRate() . '%',
            'total_time' => round($event->totalTime, 2) . 's',
        ]);

        // Example: Create a ZIP file with all successful documents
        // if ($event->isFullySuccessful()) {
        //     $this->createZipArchive($event);
        // }

        // Example: Notify about failures
        // if ($event->hasFailures()) {
        //     $this->notifyAboutFailures($event);
        // }
    }

    /**
     * Example: Create ZIP archive with all documents
     */
    protected function createZipArchive(BatchGenerationCompleted $event): string
    {
        $zipPath = storage_path('app/batches/' . $event->batchId . '.zip');
        
        // Ensure directory exists
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($event->getSuccessfulPaths() as $path) {
            if (file_exists($path)) {
                $zip->addFile($path, basename($path));
            }
        }

        $zip->close();

        Log::info('Batch ZIP created', ['path' => $zipPath]);

        return $zipPath;
    }

    /**
     * Example: Notify about failures
     */
    protected function notifyAboutFailures(BatchGenerationCompleted $event): void
    {
        $failures = $event->getFailedResults();
        
        Log::warning('Batch had failures', [
            'batch_id' => $event->batchId,
            'failures' => $failures,
        ]);

        // Send admin notification, etc.
    }
}
