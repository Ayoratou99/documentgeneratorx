<?php

namespace Ayoratoumvone\Documentgeneratorx\Loaders;

use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;

class TemplateLoader
{
    /**
     * Load template from file path or URL
     */
    public function load(string $source): string
    {
        // Check if it's a URL
        if ($this->isUrl($source)) {
            return $this->loadFromUrl($source);
        }
        
        // Check if it's a file path
        if ($this->isFilePath($source)) {
            return $this->loadFromFile($source);
        }
        
        throw new DocumentGeneratorException("Invalid template source: {$source}");
    }

    /**
     * Check if source is a URL
     */
    public function isUrl(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false &&
               (str_starts_with($source, 'http://') || str_starts_with($source, 'https://'));
    }

    /**
     * Check if source is a file path
     */
    protected function isFilePath(string $source): bool
    {
        return file_exists($source);
    }

    /**
     * Load template from URL
     */
    protected function loadFromUrl(string $url): string
    {
        try {
            // Try using Laravel's Http facade if available
            if (class_exists('\Illuminate\Support\Facades\Http')) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);
                    
                    if ($response->successful()) {
                        $tempPath = $this->createTempFile($response->body(), $this->getExtensionFromUrl($url));
                        return $tempPath;
                    }
                } catch (\Exception $e) {
                    // Fall through to alternative methods
                }
            }
            
            // Fallback: Use cURL if available
            if (function_exists('curl_init')) {
                return $this->downloadWithCurl($url);
            }
            
            // Fallback: Use file_get_contents
            return $this->downloadWithFileGetContents($url);
            
        } catch (\Exception $e) {
            throw new DocumentGeneratorException(
                "Error loading template from URL: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Download template using cURL
     */
    protected function downloadWithCurl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DocumentGeneratorX/1.0');
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || $httpCode !== 200) {
            throw new DocumentGeneratorException(
                "Failed to download template from URL: {$url} (HTTP {$httpCode})" . ($error ? " - {$error}" : "")
            );
        }
        
        return $this->createTempFile($content, $this->getExtensionFromUrl($url));
    }

    /**
     * Download template using file_get_contents
     */
    protected function downloadWithFileGetContents(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'DocumentGeneratorX/1.0',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new DocumentGeneratorException(
                "Failed to download template from URL: {$url}"
            );
        }
        
        return $this->createTempFile($content, $this->getExtensionFromUrl($url));
    }

    /**
     * Load template from file path
     */
    protected function loadFromFile(string $path): string
    {
        if (!is_readable($path)) {
            throw new DocumentGeneratorException("Template file is not readable: {$path}");
        }
        
        return $path;
    }

    /**
     * Create temporary file with content
     */
    protected function createTempFile(string $content, string $extension): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'template_') . '.' . $extension;
        
        if (file_put_contents($tempFile, $content) === false) {
            throw new DocumentGeneratorException("Failed to create temporary template file");
        }
        
        return $tempFile;
    }

    /**
     * Get file extension from URL
     */
    protected function getExtensionFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        return $extension ?: 'tmp';
    }

    /**
     * Detect template type (docx or html)
     * 
     * Supported templates: DOCX, HTML
     * Output: Always PDF
     */
    public function detectType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'docx', 'doc' => 'docx',
            'html', 'htm' => 'html',
            default => throw new DocumentGeneratorException("Unsupported template format: {$extension}. Supported: docx, html"),
        };
    }

    /**
     * Validate template format
     * 
     * Accepts DOCX and HTML templates
     */
    public function validate(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // Check by extension first
        if (in_array($extension, ['docx', 'doc', 'html', 'htm'])) {
            return true;
        }
        
        // Fallback to MIME type check
        $mimeType = mime_content_type($path);
        
        $validMimeTypes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
            'application/msword', // DOC
            'text/html', // HTML
            'application/xhtml+xml', // XHTML
        ];
        
        return in_array($mimeType, $validMimeTypes);
    }
}