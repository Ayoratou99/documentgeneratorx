<?php

namespace Ayoratoumvone\Documentgeneratorx\Jobs;

use Ayoratoumvone\Documentgeneratorx\Events\BatchGenerationCompleted;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * Helper class for dispatching batch document generation jobs
 * 
 * This class simplifies creating and dispatching multiple document
 * generation jobs that run in parallel using Laravel's queue system.
 * 
 * Usage:
 *   // Create batch and get document IDs for tracking
 *   $batchHelper = GenerateDocumentBatch::create([
 *       ['template' => 'invoice.docx', 'variables' => ['name' => 'Alice'], 'output' => 'alice.pdf'],
 *       ['template' => 'invoice.docx', 'variables' => ['name' => 'Bob'], 'output' => 'bob.pdf'],
 *   ]);
 *   
 *   // Get document IDs BEFORE dispatching (store these in your database)
 *   $documentIds = $batchHelper->getDocumentIds();
 *   // ['doc_abc123...', 'doc_def456...']
 *   
 *   // Save to your database for tracking
 *   foreach ($documentIds as $index => $documentId) {
 *       DocumentRequest::create([
 *           'document_id' => $documentId,
 *           'user_id' => auth()->id(),
 *           'status' => 'pending',
 *       ]);
 *   }
 *   
 *   // Now dispatch the batch
 *   $batch = $batchHelper->dispatch();
 */
class GenerateDocumentBatch
{
    protected array $jobs = [];
    protected string $batchId;
    protected ?string $queue = null;
    protected ?string $connection = null;
    protected ?\Closure $thenCallback = null;
    protected ?\Closure $catchCallback = null;
    protected ?\Closure $finallyCallback = null;
    protected bool $allowFailures = false;
    protected string $name = 'Document Generation Batch';
    protected array $documents = [];
    protected float $startTime;

    /**
     * Create a new batch instance
     *
     * @param array $documents Array of document configurations:
     *   [
     *     ['template' => '...', 'variables' => [...], 'output' => '...', 'disk' => null],
     *     ...
     *   ]
     */
    public function __construct(array $documents)
    {
        $this->batchId = Str::uuid()->toString();
        $this->documents = $documents;
        $this->startTime = microtime(true);

        foreach ($documents as $doc) {
            $this->jobs[] = new GenerateDocument(
                templatePath: $doc['template'],
                variables: $doc['variables'] ?? [],
                outputPath: $doc['output'] ?? null,
                disk: $doc['disk'] ?? null,
                batchId: $this->batchId
            );
        }
    }

    /**
     * Static factory method
     */
    public static function create(array $documents): self
    {
        return new self($documents);
    }

    /**
     * Get all document IDs for tracking
     * 
     * Call this BEFORE dispatch() to get the IDs you need to store in your database.
     * These IDs will be included in all events (DocumentGenerated, DocumentGenerationFailed)
     * so you can match events to your database records.
     * 
     * @return array Array of document IDs in the same order as the input documents
     */
    public function getDocumentIds(): array
    {
        return array_map(fn($job) => $job->documentId, $this->jobs);
    }

    /**
     * Get document IDs mapped to their output paths
     * 
     * @return array ['doc_id' => 'output_path', ...]
     */
    public function getDocumentIdsWithOutputs(): array
    {
        $result = [];
        foreach ($this->jobs as $job) {
            $result[$job->documentId] = $job->outputPath;
        }
        return $result;
    }

    /**
     * Set the queue to use
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set the connection to use
     */
    public function onConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Callback when all jobs complete successfully
     */
    public function then(\Closure $callback): self
    {
        $this->thenCallback = $callback;
        return $this;
    }

    /**
     * Callback when first job fails
     */
    public function catch(\Closure $callback): self
    {
        $this->catchCallback = $callback;
        return $this;
    }

    /**
     * Callback when batch finishes (success or failure)
     */
    public function finally(\Closure $callback): self
    {
        $this->finallyCallback = $callback;
        return $this;
    }

    /**
     * Allow the batch to continue even if jobs fail
     */
    public function allowFailures(bool $allow = true): self
    {
        $this->allowFailures = $allow;
        return $this;
    }

    /**
     * Set a name for the batch
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the batch ID
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Dispatch the batch
     */
    public function dispatch(): Batch
    {
        $startTime = $this->startTime;
        $batchId = $this->batchId;
        $totalDocuments = count($this->documents);

        $batch = Bus::batch($this->jobs)
            ->name($this->name);

        if ($this->queue) {
            $batch->onQueue($this->queue);
        }

        if ($this->connection) {
            $batch->onConnection($this->connection);
        }

        if ($this->allowFailures) {
            $batch->allowFailures();
        }

        // Wrap the user's then callback to fire our event
        $userThenCallback = $this->thenCallback;
        $batch->then(function (Batch $batch) use ($userThenCallback, $startTime, $batchId, $totalDocuments) {
            $totalTime = microtime(true) - $startTime;
            
            event(new BatchGenerationCompleted(
                $batchId,
                [], // Results would need to be tracked separately
                $totalDocuments,
                $totalDocuments - $batch->failedJobs,
                $batch->failedJobs,
                $totalTime
            ));

            if ($userThenCallback) {
                $userThenCallback($batch);
            }
        });

        if ($this->catchCallback) {
            $batch->catch($this->catchCallback);
        }

        if ($this->finallyCallback) {
            $batch->finally($this->finallyCallback);
        }

        return $batch->dispatch();
    }

    /**
     * Get the number of jobs in the batch
     */
    public function count(): int
    {
        return count($this->jobs);
    }
}
