<?php

namespace Ayoratoumvone\Documentgeneratorx\Jobs;

use Ayoratoumvone\Documentgeneratorx\DocumentGenerator;
use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerating;
use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerated;
use Ayoratoumvone\Documentgeneratorx\Events\DocumentGenerationFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Queue job for generating documents asynchronously
 * 
 * This job allows you to generate PDF documents in the background
 * using Laravel's queue system. Perfect for:
 * - Generating multiple documents simultaneously
 * - Long-running document generation
 * - Offloading work from web requests
 * 
 * Usage:
 *   // Create job and get the document ID for tracking
 *   $job = new GenerateDocument('template.docx', ['name' => 'John'], 'output.pdf');
 *   $documentId = $job->documentId;  // System-generated ID to track this document
 *   dispatch($job);
 *   
 *   // Store $documentId in your database to track progress via events
 * 
 * With batch:
 *   $jobs = [
 *       new GenerateDocument('template.docx', ['name' => 'John'], 'john.pdf'),
 *       new GenerateDocument('template.docx', ['name' => 'Jane'], 'jane.pdf'),
 *   ];
 *   
 *   // Get all document IDs before dispatching
 *   $documentIds = array_map(fn($job) => $job->documentId, $jobs);
 *   
 *   Bus::batch($jobs)->dispatch();
 */
class GenerateDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    /**
     * System-generated unique document identifier for tracking
     * Use this ID to track the document's progress in your event listeners
     */
    public string $documentId;

    /**
     * Create a new job instance.
     *
     * @param string $templatePath Path to the template file (absolute or relative to storage)
     * @param array $variables Variables to replace in the template
     * @param string|null $outputPath Output path for the generated PDF
     * @param string|null $disk Storage disk for output (null = local filesystem)
     * @param string|null $batchId Optional batch identifier for grouping jobs
     */
    public function __construct(
        public string $templatePath,
        public array $variables,
        public ?string $outputPath = null,
        public ?string $disk = null,
        public ?string $batchId = null
    ) {
        // System generates unique document ID for tracking
        $this->documentId = 'doc_' . Str::uuid()->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        // Fire "generating" event with documentId for tracking
        event(new DocumentGenerating(
            $this->templatePath,
            $this->variables,
            $this->outputPath,
            $this->documentId
        ));

        try {
            $generator = new DocumentGenerator();
            
            // Generate the document
            if ($this->disk) {
                $outputPath = $generator
                    ->template($this->templatePath)
                    ->variables($this->variables)
                    ->generateToStorage($this->outputPath, $this->disk);
            } else {
                $outputPath = $generator
                    ->template($this->templatePath)
                    ->variables($this->variables)
                    ->permanent() // Don't auto-delete queued documents
                    ->generate($this->outputPath);
            }

            $generationTime = microtime(true) - $startTime;
            $fileSize = file_exists($outputPath) ? filesize($outputPath) : 0;

            // Fire "generated" event with documentId for tracking
            event(new DocumentGenerated(
                $outputPath,
                $this->templatePath,
                $this->variables,
                $generationTime,
                $fileSize,
                $this->documentId
            ));

        } catch (Throwable $e) {
            // Fire "failed" event with documentId for tracking
            event(new DocumentGenerationFailed(
                $this->templatePath,
                $this->variables,
                $this->outputPath,
                $e,
                $this->documentId
            ));

            throw $e; // Re-throw for queue retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        // This is called after all retries have been exhausted
        event(new DocumentGenerationFailed(
            $this->templatePath,
            $this->variables,
            $this->outputPath,
            $exception,
            $this->documentId
        ));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['document-generator', 'doc:' . $this->documentId];
        
        if ($this->batchId) {
            $tags[] = 'batch:' . $this->batchId;
        }
        
        return $tags;
    }
}
