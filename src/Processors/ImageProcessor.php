<?php

namespace Ayoratoumvone\Documentgeneratorx\Processors;

use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessor
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Process image (from URL or file) and return processed file path
     */
    public function process(string $source, array $dimensions = []): array
    {
        $imagePath = $this->loadImage($source);
        
        if (!empty($dimensions)) {
            $imagePath = $this->resizeImage($imagePath, $dimensions);
        }
        
        return [
            'path' => $imagePath,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
        ];
    }

    /**
     * Load image from URL or file path
     */
    protected function loadImage(string $source): string
    {
        // Check if it's a URL
        if ($this->isUrl($source)) {
            return $this->downloadImage($source);
        }
        
        // Check if it's a file
        if (file_exists($source)) {
            return $source;
        }
        
        throw new DocumentGeneratorException("Image not found: {$source}");
    }

    /**
     * Check if source is a URL
     */
    protected function isUrl(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Download image from URL
     */
    protected function downloadImage(string $url): string
    {
        try {
            // Try using Laravel's Http facade if available
            if (class_exists('\Illuminate\Support\Facades\Http')) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);
                    
                    if ($response->successful()) {
                        $extension = $this->getImageExtension($url, $response->header('Content-Type'));
                        $tempPath = tempnam(sys_get_temp_dir(), 'img_') . '.' . $extension;
                        file_put_contents($tempPath, $response->body());
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
            
            // Fallback: Use file_get_contents with context
            return $this->downloadWithFileGetContents($url);
            
        } catch (\Exception $e) {
            throw new DocumentGeneratorException(
                "Error downloading image: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Download image using cURL
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
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || $httpCode !== 200) {
            throw new DocumentGeneratorException(
                "Failed to download image from URL: {$url} (HTTP {$httpCode})" . ($error ? " - {$error}" : "")
            );
        }
        
        $extension = $this->getImageExtension($url, $contentType);
        $tempPath = tempnam(sys_get_temp_dir(), 'img_') . '.' . $extension;
        file_put_contents($tempPath, $content);
        
        return $tempPath;
    }

    /**
     * Download image using file_get_contents
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
                "Failed to download image from URL: {$url}"
            );
        }
        
        // Get content type from headers
        $contentType = null;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $contentType = trim(substr($header, 13));
                    break;
                }
            }
        }
        
        $extension = $this->getImageExtension($url, $contentType);
        $tempPath = tempnam(sys_get_temp_dir(), 'img_') . '.' . $extension;
        file_put_contents($tempPath, $content);
        
        return $tempPath;
    }

    /**
     * Resize image according to dimensions
     */
    protected function resizeImage(string $imagePath, array $dimensions): string
    {
        try {
            $image = $this->imageManager->read($imagePath);
            
            // Handle ratio-based resizing
            if (isset($dimensions['ratio'])) {
                $ratio = $dimensions['ratio'];
                $currentWidth = $image->width();
                $currentHeight = $image->height();
                
                // Calculate dimensions maintaining aspect ratio
                $targetRatio = $ratio['width'] / $ratio['height'];
                $currentRatio = $currentWidth / $currentHeight;
                
                if ($currentRatio > $targetRatio) {
                    // Image is wider, fit to height
                    $newHeight = $dimensions['height'] ?? 400;
                    $newWidth = (int) ($newHeight * $targetRatio);
                } else {
                    // Image is taller, fit to width
                    $newWidth = $dimensions['width'] ?? 600;
                    $newHeight = (int) ($newWidth / $targetRatio);
                }
                
                $image->scale($newWidth, $newHeight);
            } else {
                // Direct width/height resize
                $width = $dimensions['width'] ?? null;
                $height = $dimensions['height'] ?? null;
                
                if ($width && $height) {
                    $image->scale($width, $height);
                } elseif ($width) {
                    $image->scaleDown($width);
                } elseif ($height) {
                    $image->scaleDown(height: $height);
                }
            }
            
            // Save resized image
            $resizedPath = tempnam(sys_get_temp_dir(), 'resized_') . '.png';
            $image->save($resizedPath);
            
            // Clean up original if it was a temp file
            if (str_starts_with(basename($imagePath), 'img_')) {
                @unlink($imagePath);
            }
            
            return $resizedPath;
        } catch (\Exception $e) {
            throw new DocumentGeneratorException(
                "Error resizing image: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Get image extension from URL or content type
     */
    protected function getImageExtension(string $url, ?string $contentType = null): string
    {
        // Try to get from URL first
        $pathExtension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($pathExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return $pathExtension;
        }
        
        // Try to get from content type
        if ($contentType) {
            $typeMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            
            return $typeMap[$contentType] ?? 'jpg';
        }
        
        return 'jpg'; // Default
    }

    /**
     * Get image dimensions
     */
    public function getDimensions(string $imagePath): array
    {
        $imageInfo = getimagesize($imagePath);
        
        if ($imageInfo === false) {
            throw new DocumentGeneratorException("Failed to get image dimensions");
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }

    /**
     * Validate image file
     */
    public function validate(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            return false;
        }
        
        $imageInfo = @getimagesize($imagePath);
        return $imageInfo !== false;
    }
}