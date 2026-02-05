<?php

namespace Ayoratoumvone\Documentgeneratorx;

use Ayoratoumvone\Documentgeneratorx\Contracts\GeneratorInterface;
use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Generators\HtmlToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Loaders\TemplateLoader;
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;
use Illuminate\Support\Facades\Storage;

/**
 * DocumentGeneratorX - PDF Document Generator
 * 
 * Generate PDF documents from DOCX or HTML templates with variable replacement.
 * 
 * @package Ayoratoumvone\Documentgeneratorx
 */
class DocumentGenerator
{
    protected array $variables = [];
    protected ?string $templatePath = null;
    protected ?string $templateSource = null;
    protected ?string $templateFormat = null;
    protected ?GeneratorInterface $generator = null;
    protected TemplateLoader $templateLoader;
    protected bool $isTemporaryTemplate = false;
    protected ?string $lastGeneratedPath = null;
    protected bool $tempOutput;
    protected bool $cleanupOnShutdown;
    protected bool $deleteAfterDownload;

    public function __construct()
    {
        $this->templateLoader = new TemplateLoader();
        
        // Load config options
        $this->tempOutput = config('documentgenerator.temp_output', true);
        $this->cleanupOnShutdown = config('documentgenerator.cleanup_on_shutdown', true);
        $this->deleteAfterDownload = config('documentgenerator.delete_after_download', true);
    }

    /**
     * Set the template file path or URL
     * 
     * Supported formats: DOCX, HTML
     */
    public function template(string $source): self
    {
        $this->templateSource = $source;
        
        // Load template (handles both file paths and URLs)
        $this->templatePath = $this->templateLoader->load($source);
        
        // Mark as temporary if downloaded from URL
        $this->isTemporaryTemplate = $this->templateLoader->isUrl($source);
        
        // Detect template format
        $this->templateFormat = $this->templateLoader->detectType($this->templatePath);
        
        // Validate template
        if (!$this->templateLoader->validate($this->templatePath)) {
            throw new DocumentGeneratorException("Invalid template format: {$source}");
        }
        
        return $this;
    }

    /**
     * Set template from storage disk
     */
    public function templateFromStorage(string $path, string $disk = 'local'): self
    {
        $fullPath = Storage::disk($disk)->path($path);
        return $this->template($fullPath);
    }

    /**
     * Set variables to replace in template
     */
    public function variables(array $variables): self
    {
        $this->variables = $variables;
        return $this;
    }

    /**
     * Add a single variable
     */
    public function addVariable(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Set whether output should be temporary (overrides config)
     */
    public function temporary(bool $temp = true): self
    {
        $this->tempOutput = $temp;
        return $this;
    }

    /**
     * Set output as permanent (not temporary)
     */
    public function permanent(): self
    {
        $this->tempOutput = false;
        return $this;
    }

    /**
     * Generate the PDF document and return file path
     */
    public function generate(string $outputPath = null): string
    {
        if (!$this->templatePath) {
            throw new DocumentGeneratorException('Template path is not set');
        }

        // Create generator based on template format
        $this->generator = $this->createGenerator();

        // Generate output path if not provided
        if (!$outputPath) {
            $outputPath = $this->generateOutputPath();
        }

        // Ensure .pdf extension
        if (!str_ends_with(strtolower($outputPath), '.pdf')) {
            $outputPath .= '.pdf';
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Generate the PDF document
        $this->generator->generate(
            $this->templatePath,
            $this->variables,
            $outputPath
        );
        
        // Track the generated file
        $this->lastGeneratedPath = $outputPath;
        
        // Register for cleanup if temp output is enabled
        if ($this->tempOutput && $this->cleanupOnShutdown) {
            $this->registerForCleanup($outputPath);
        }
        
        // Clean up temporary template if needed
        $this->cleanupTemporaryTemplate();

        return $outputPath;
    }

    /**
     * Generate and save to storage (permanent, not affected by temp_output)
     */
    public function generateToStorage(string $path, string $disk = 'local'): string
    {
        // Force temp output for intermediate file
        $originalTempOutput = $this->tempOutput;
        $this->tempOutput = true;
        
        $tempPath = $this->generate();
        
        // Restore original setting
        $this->tempOutput = $originalTempOutput;
        
        Storage::disk($disk)->put($path, file_get_contents($tempPath));
        
        // Clean up temp file immediately
        @unlink($tempPath);
        
        return Storage::disk($disk)->path($path);
    }

    /**
     * Generate and return as download response
     */
    public function download(string $filename = null): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filePath = $this->generate();
        
        if (!$filename) {
            $filename = 'document_' . time() . '.pdf';
        }

        // Ensure .pdf extension
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        // Delete after download if configured
        return response()->download($filePath, $filename)
            ->deleteFileAfterSend($this->deleteAfterDownload);
    }

    /**
     * Create generator instance based on template format
     */
    protected function createGenerator(): GeneratorInterface
    {
        return match ($this->templateFormat) {
            'docx' => new DocxToPdfGenerator(),
            'html' => new HtmlToPdfGenerator(),
            default => throw new DocumentGeneratorException("Unsupported template format: {$this->templateFormat}")
        };
    }

    /**
     * Generate output file path
     */
    protected function generateOutputPath(): string
    {
        if ($this->tempOutput) {
            // Use system temp directory for temporary files
            $dir = sys_get_temp_dir();
        } else {
            // Use configured output path for permanent files
            $dir = config('documentgenerator.output_path', storage_path('app/generated-documents'));
            
            // Ensure directory exists
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        $filename = 'document_' . uniqid() . '.pdf';
        
        return $dir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Register a file for cleanup on script shutdown
     */
    protected function registerForCleanup(string $path): void
    {
        register_shutdown_function(function () use ($path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        });
    }

    /**
     * Clean up temporary template files
     */
    protected function cleanupTemporaryTemplate(): void
    {
        if ($this->isTemporaryTemplate && $this->templatePath) {
            register_shutdown_function(function () {
                if (file_exists($this->templatePath)) {
                    @unlink($this->templatePath);
                }
            });
        }
    }

    /**
     * Manually delete the last generated file
     */
    public function cleanup(): self
    {
        if ($this->lastGeneratedPath && file_exists($this->lastGeneratedPath)) {
            @unlink($this->lastGeneratedPath);
            $this->lastGeneratedPath = null;
        }
        return $this;
    }

    /**
     * Get the path of the last generated file
     */
    public function getLastGeneratedPath(): ?string
    {
        return $this->lastGeneratedPath;
    }

    /**
     * Get current variables
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Get template format (docx or html)
     */
    public function getTemplateFormat(): ?string
    {
        return $this->templateFormat;
    }

    /**
     * Check if output is set to temporary
     */
    public function isTemporary(): bool
    {
        return $this->tempOutput;
    }

    /**
     * Reset the generator state
     */
    public function reset(): self
    {
        // Clean up before reset
        $this->cleanup();
        $this->cleanupTemporaryTemplate();
        
        $this->variables = [];
        $this->templatePath = null;
        $this->templateSource = null;
        $this->templateFormat = null;
        $this->generator = null;
        $this->isTemporaryTemplate = false;
        $this->lastGeneratedPath = null;
        
        // Reload config
        $this->tempOutput = config('documentgenerator.temp_output', true);
        
        return $this;
    }

    /**
     * Generate multiple documents from the same template with different variables
     * 
     * @param array $documentsData Array of document configurations:
     *   [
     *     ['variables' => [...], 'output' => 'path/to/file.pdf'],
     *     ['variables' => [...], 'output' => 'path/to/file2.pdf'],
     *   ]
     * @param bool $parallel Whether to attempt parallel generation (requires pcntl extension)
     * @param callable|null $onProgress Optional callback for progress: function(int $completed, int $total, string $path)
     * 
     * @return array Results with paths and any errors:
     *   [
     *     ['success' => true, 'path' => '...', 'output' => '...'],
     *     ['success' => false, 'error' => '...', 'output' => '...'],
     *   ]
     */
    public function generateBatch(array $documentsData, bool $parallel = false, ?callable $onProgress = null): array
    {
        if (!$this->templatePath) {
            throw new DocumentGeneratorException('Template path is not set. Call template() first.');
        }

        if (empty($documentsData)) {
            return [];
        }

        // Use parallel processing if requested and available
        if ($parallel && $this->canUseParallel()) {
            return $this->generateBatchParallel($documentsData, $onProgress);
        }

        // Sequential processing (always available)
        return $this->generateBatchSequential($documentsData, $onProgress);
    }

    /**
     * Generate batch documents sequentially
     */
    protected function generateBatchSequential(array $documentsData, ?callable $onProgress = null): array
    {
        $results = [];
        $total = count($documentsData);
        $completed = 0;

        foreach ($documentsData as $index => $docData) {
            $variables = $docData['variables'] ?? [];
            $outputPath = $docData['output'] ?? null;

            try {
                // Set variables for this document
                $this->variables = $variables;

                // Generate
                $path = $this->generate($outputPath);

                $results[] = [
                    'success' => true,
                    'path' => $path,
                    'output' => $outputPath,
                    'index' => $index,
                ];

                $completed++;
                
                if ($onProgress) {
                    $onProgress($completed, $total, $path);
                }

            } catch (\Throwable $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'output' => $outputPath,
                    'index' => $index,
                ];

                $completed++;
                
                if ($onProgress) {
                    $onProgress($completed, $total, null);
                }
            }
        }

        return $results;
    }

    /**
     * Generate batch documents in parallel using pcntl_fork
     */
    protected function generateBatchParallel(array $documentsData, ?callable $onProgress = null): array
    {
        $total = count($documentsData);
        $maxWorkers = min(4, $total); // Limit concurrent workers
        $results = [];
        $tempFiles = [];

        // Create temp file for IPC (inter-process communication)
        $ipcFile = tempnam(sys_get_temp_dir(), 'docgen_ipc_');

        // Split documents into chunks for workers
        $chunks = array_chunk($documentsData, (int) ceil($total / $maxWorkers), true);
        $pids = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed, fall back to sequential
                @unlink($ipcFile);
                return $this->generateBatchSequential($documentsData, $onProgress);
            } elseif ($pid === 0) {
                // Child process
                $childResults = [];

                foreach ($chunk as $index => $docData) {
                    $variables = $docData['variables'] ?? [];
                    $outputPath = $docData['output'] ?? null;

                    try {
                        $this->variables = $variables;
                        $path = $this->generate($outputPath);

                        $childResults[] = [
                            'success' => true,
                            'path' => $path,
                            'output' => $outputPath,
                            'index' => $index,
                        ];
                    } catch (\Throwable $e) {
                        $childResults[] = [
                            'success' => false,
                            'error' => $e->getMessage(),
                            'output' => $outputPath,
                            'index' => $index,
                        ];
                    }
                }

                // Write results to IPC file
                $resultFile = $ipcFile . '_' . $chunkIndex;
                file_put_contents($resultFile, serialize($childResults));
                exit(0);
            } else {
                // Parent process
                $pids[$chunkIndex] = $pid;
                $tempFiles[] = $ipcFile . '_' . $chunkIndex;
            }
        }

        // Wait for all children to complete
        $completed = 0;
        foreach ($pids as $chunkIndex => $pid) {
            pcntl_waitpid($pid, $status);

            // Read results from child
            $resultFile = $ipcFile . '_' . $chunkIndex;
            if (file_exists($resultFile)) {
                $childResults = unserialize(file_get_contents($resultFile));
                foreach ($childResults as $result) {
                    $results[] = $result;
                    $completed++;
                    
                    if ($onProgress) {
                        $onProgress($completed, $total, $result['path'] ?? null);
                    }
                }
                @unlink($resultFile);
            }
        }

        // Clean up IPC file
        @unlink($ipcFile);

        // Sort results by original index
        usort($results, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return $results;
    }

    /**
     * Check if parallel processing is available
     */
    protected function canUseParallel(): bool
    {
        return function_exists('pcntl_fork') && PHP_OS_FAMILY !== 'Windows';
    }

    /**
     * Static method to generate multiple documents from different templates
     * 
     * @param array $documents Array of document configurations:
     *   [
     *     ['template' => 'path/to/template.docx', 'variables' => [...], 'output' => 'output.pdf'],
     *     ['template' => 'path/to/other.docx', 'variables' => [...], 'output' => 'output2.pdf'],
     *   ]
     * @param callable|null $onProgress Optional progress callback
     * 
     * @return array Results array
     */
    public static function batchGenerate(array $documents, ?callable $onProgress = null): array
    {
        $results = [];
        $total = count($documents);
        $completed = 0;

        foreach ($documents as $index => $docConfig) {
            $template = $docConfig['template'] ?? null;
            $variables = $docConfig['variables'] ?? [];
            $outputPath = $docConfig['output'] ?? null;

            if (!$template) {
                $results[] = [
                    'success' => false,
                    'error' => 'Template path is required',
                    'output' => $outputPath,
                    'index' => $index,
                ];
                $completed++;
                continue;
            }

            try {
                $generator = new self();
                $path = $generator
                    ->template($template)
                    ->variables($variables)
                    ->generate($outputPath);

                $results[] = [
                    'success' => true,
                    'path' => $path,
                    'output' => $outputPath,
                    'index' => $index,
                ];

                $completed++;
                
                if ($onProgress) {
                    $onProgress($completed, $total, $path);
                }

            } catch (\Throwable $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'output' => $outputPath,
                    'index' => $index,
                ];

                $completed++;
                
                if ($onProgress) {
                    $onProgress($completed, $total, null);
                }
            }
        }

        return $results;
    }
}
