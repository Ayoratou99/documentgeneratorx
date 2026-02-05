<?php

namespace App\Listeners\DocumentGenerator;

use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerationFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
// use App\Models\DocumentRequest;

/**
 * Example listener: Handle document generation failure
 * 
 * Use $event->documentId to find the related record in your database.
 * 
 * Register this listener in your EventServiceProvider:
 * 
 *   protected $listen = [
 *       \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerationFailed::class => [
 *           \App\Listeners\DocumentGenerator\HandleGenerationFailed::class,
 *       ],
 *   ];
 */
class HandleGenerationFailed implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(DocumentGenerationFailed $event): void
    {
        Log::error('Document generation failed', [
            'document_id' => $event->documentId,
            'template' => $event->templatePath,
            'output' => $event->outputPath,
            'error' => $event->getErrorMessage(),
            'exception_type' => $event->getExceptionType(),
            'variables' => $this->sanitizeVariables($event->variables),
        ]);

        // Example: Update your document request record
        // $documentRequest = DocumentRequest::where('document_id', $event->documentId)->first();
        // 
        // if ($documentRequest) {
        //     $documentRequest->update([
        //         'status' => 'failed',
        //         'error_message' => $event->getErrorMessage(),
        //         'failed_at' => now(),
        //     ]);
        //     
        //     // Notify the user about the failure
        //     $documentRequest->user->notify(new DocumentFailedNotification(
        //         $event->getErrorMessage()
        //     ));
        // }

        // Available event properties:
        // $event->documentId       - System-generated ID to track this document
        // $event->templatePath     - Template that was used
        // $event->outputPath       - Intended output path
        // $event->variables        - Variables that were used
        // $event->exception        - The exception that was thrown
        // $event->getErrorMessage()   - Error message string
        // $event->getExceptionType()  - Exception class name
    }

    /**
     * Remove sensitive data from variables before logging
     */
    protected function sanitizeVariables(array $variables): array
    {
        $sensitive = ['password', 'secret', 'token', 'key', 'ssn', 'credit_card'];
        
        foreach ($variables as $key => $value) {
            foreach ($sensitive as $term) {
                if (stripos($key, $term) !== false) {
                    $variables[$key] = '[REDACTED]';
                    break;
                }
            }
        }
        
        return $variables;
    }
}
