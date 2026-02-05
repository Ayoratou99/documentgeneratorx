<?php

namespace Ayoratoumvone\Documentgeneratorx\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired before a document starts generating
 * 
 * Use this event to:
 * - Log document generation attempts
 * - Update document status in your database (e.g., status = 'processing')
 * - Validate variables before generation
 * - Track generation queue status
 */
class DocumentGenerating
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $templatePath Path to the template file
     * @param array $variables Variables to be replaced in the template
     * @param string|null $outputPath Intended output path (may be null if auto-generated)
     * @param string|null $documentId System-generated unique identifier to track this document
     */
    public function __construct(
        public string $templatePath,
        public array $variables,
        public ?string $outputPath = null,
        public ?string $documentId = null
    ) {}
}
