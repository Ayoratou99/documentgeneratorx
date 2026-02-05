<?php

namespace App\Listeners\DocumentGenerator;

use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated;
use Illuminate\Support\Facades\Log;

/**
 * Example listener: Log when a document is generated
 * 
 * Register this listener in your EventServiceProvider:
 * 
 *   protected $listen = [
 *       \Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated::class => [
 *           \App\Listeners\DocumentGenerator\LogDocumentGenerated::class,
 *       ],
 *   ];
 */
class LogDocumentGenerated
{
    /**
     * Handle the event.
     */
    public function handle(DocumentGenerated $event): void
    {
        Log::info('Document generated successfully', [
            'document_id' => $event->documentId,
            'output_path' => $event->outputPath,
            'template' => $event->templatePath,
            'file_size' => $event->getFileSizeFormatted(),
            'generation_time' => $event->getGenerationTimeFormatted(),
        ]);
    }
}
