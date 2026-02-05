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

    public function __construct()
    {
        $this->templateLoader = new TemplateLoader();
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
        
        // Clean up temporary template if needed
        $this->cleanupTemporaryFiles();

        return $outputPath;
    }

    /**
     * Generate and save to storage
     */
    public function generateToStorage(string $path, string $disk = 'local'): string
    {
        $tempPath = $this->generate();
        
        Storage::disk($disk)->put($path, file_get_contents($tempPath));
        
        // Clean up temp file
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

        return response()->download($filePath, $filename)->deleteFileAfterSend(true);
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
     * Generate output file path (always PDF)
     */
    protected function generateOutputPath(): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = 'document_' . uniqid() . '.pdf';
        
        return $tempDir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Clean up temporary files
     */
    protected function cleanupTemporaryFiles(): void
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
     * Reset the generator state
     */
    public function reset(): self
    {
        // Clean up before reset
        $this->cleanupTemporaryFiles();
        
        $this->variables = [];
        $this->templatePath = null;
        $this->templateSource = null;
        $this->templateFormat = null;
        $this->generator = null;
        $this->isTemporaryTemplate = false;
        
        return $this;
    }
}