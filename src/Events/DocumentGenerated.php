<?php

namespace Ayoratoumvone\Documentgeneratorx\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired after a document has been successfully generated
 * 
 * Use this event to:
 * - Update document status in your database (e.g., status = 'completed')
 * - Send notifications when documents are ready
 * - Move generated files to storage
 * - Trigger email sending with attachments
 */
class DocumentGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $outputPath Path to the generated PDF file
     * @param string $templatePath Path to the template that was used
     * @param array $variables Variables that were replaced
     * @param float $generationTime Time taken to generate (in seconds)
     * @param int $fileSize Size of generated file in bytes
     * @param string|null $documentId System-generated unique identifier to track this document
     */
    public function __construct(
        public string $outputPath,
        public string $templatePath,
        public array $variables,
        public float $generationTime,
        public int $fileSize,
        public ?string $documentId = null
    ) {}

    /**
     * Get file size in human readable format
     */
    public function getFileSizeFormatted(): string
    {
        $bytes = $this->fileSize;
        
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    /**
     * Get generation time in human readable format
     */
    public function getGenerationTimeFormatted(): string
    {
        if ($this->generationTime >= 1) {
            return round($this->generationTime, 2) . 's';
        }
        
        return round($this->generationTime * 1000) . 'ms';
    }
}
