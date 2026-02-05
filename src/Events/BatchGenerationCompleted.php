<?php

namespace Ayoratoumvone\Documentgeneratorx\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a batch of documents has completed generation
 * 
 * Use this event to:
 * - Notify users that all their documents are ready
 * - Zip multiple documents together
 * - Clean up temporary files
 * - Update batch job status
 */
class BatchGenerationCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $batchId Unique identifier for the batch
     * @param array $results Array of generation results
     * @param int $totalDocuments Total number of documents in batch
     * @param int $successCount Number of successful generations
     * @param int $failedCount Number of failed generations
     * @param float $totalTime Total time taken for the batch
     */
    public function __construct(
        public string $batchId,
        public array $results,
        public int $totalDocuments,
        public int $successCount,
        public int $failedCount,
        public float $totalTime
    ) {}

    /**
     * Check if all documents were generated successfully
     */
    public function isFullySuccessful(): bool
    {
        return $this->failedCount === 0;
    }

    /**
     * Check if any documents failed
     */
    public function hasFailures(): bool
    {
        return $this->failedCount > 0;
    }

    /**
     * Get all successful file paths
     */
    public function getSuccessfulPaths(): array
    {
        return array_column(
            array_filter($this->results, fn($r) => $r['success'] ?? false),
            'path'
        );
    }

    /**
     * Get all failed results
     */
    public function getFailedResults(): array
    {
        return array_filter($this->results, fn($r) => !($r['success'] ?? false));
    }

    /**
     * Get success rate as percentage
     */
    public function getSuccessRate(): float
    {
        if ($this->totalDocuments === 0) {
            return 0;
        }
        
        return round(($this->successCount / $this->totalDocuments) * 100, 2);
    }
}
