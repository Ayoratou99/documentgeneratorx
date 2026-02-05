<?php

namespace App\Listeners\DocumentGenerator;

use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
// use App\Models\DocumentRequest;

/**
 * Example listener: Notify user when document is ready
 * 
 * This listener runs asynchronously in the queue.
 * Use $event->documentId to find the related record in your database.
 * 
 * Register this listener in your EventServiceProvider:
 * 
 *   protected $listen = [
 *       \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated::class => [
 *           \App\Listeners\DocumentGenerator\NotifyDocumentReady::class,
 *       ],
 *   ];
 */
class NotifyDocumentReady implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(DocumentGenerated $event): void
    {
        // Use $event->documentId to find your database record
        // This ID was returned when you created the GenerateDocument job
        
        // Example: Update your document request record
        // $documentRequest = DocumentRequest::where('document_id', $event->documentId)->first();
        // 
        // if ($documentRequest) {
        //     // Update status
        //     $documentRequest->update([
        //         'status' => 'completed',
        //         'file_path' => $event->outputPath,
        //         'file_size' => $event->fileSize,
        //         'completed_at' => now(),
        //     ]);
        //     
        //     // Notify the user
        //     $documentRequest->user->notify(new DocumentReadyNotification(
        //         $event->outputPath,
        //         $event->getFileSizeFormatted()
        //     ));
        // }
        
        // Available event properties:
        // $event->documentId      - System-generated ID to track this document
        // $event->outputPath      - Path to the generated PDF
        // $event->templatePath    - Template that was used
        // $event->variables       - Variables that were replaced
        // $event->fileSize        - File size in bytes
        // $event->generationTime  - Time taken to generate (seconds)
        // $event->getFileSizeFormatted()      - e.g., "90.5 KB"
        // $event->getGenerationTimeFormatted() - e.g., "2.5s"
    }
}
