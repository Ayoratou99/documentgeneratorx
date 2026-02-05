<?php

namespace Ayoratoumvone\Documentgeneratorx\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when document generation fails
 * 
 * Use this event to:
 * - Update document status in your database (e.g., status = 'failed')
 * - Log errors for debugging
 * - Notify administrators of failures
 * - Retry failed generations
 */
class DocumentGenerationFailed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $templatePath Path to the template file
     * @param array $variables Variables that were used
     * @param string|null $outputPath Intended output path
     * @param Throwable $exception The exception that caused the failure
     * @param string|null $documentId System-generated unique identifier to track this document
     */
    public function __construct(
        public string $templatePath,
        public array $variables,
        public ?string $outputPath,
        public Throwable $exception,
        public ?string $documentId = null
    ) {}

    /**
     * Get the error message
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the exception class name
     */
    public function getExceptionType(): string
    {
        return get_class($this->exception);
    }
}
