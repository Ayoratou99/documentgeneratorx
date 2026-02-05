<?php

namespace Ayoratoumvone\Documentgeneratorx\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when document generation fails
 * 
 * This exception is used throughout the document generator library
 * to indicate errors during template loading, parsing, or generation.
 */
class DocumentGeneratorException extends Exception
{
    /**
     * Create a new DocumentGeneratorException
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param Throwable|null $previous The previous exception for chaining
     */
    public function __construct(
        string $message = "Document generation failed",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for invalid template
     */
    public static function invalidTemplate(string $path): self
    {
        return new self("Invalid or unsupported template: {$path}");
    }

    /**
     * Create exception for template not found
     */
    public static function templateNotFound(string $path): self
    {
        return new self("Template file not found: {$path}");
    }

    /**
     * Create exception for unsupported format
     */
    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported document format: {$format}");
    }

    /**
     * Create exception for invalid variable type
     */
    public static function invalidVariableType(string $variable, string $expectedType): self
    {
        return new self("Invalid value type for variable '{$variable}'. Expected: {$expectedType}");
    }

    /**
     * Create exception for image processing errors
     */
    public static function imageProcessingFailed(string $source, string $reason = ''): self
    {
        $message = "Failed to process image: {$source}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for download failures
     */
    public static function downloadFailed(string $url, int $statusCode = 0): self
    {
        $message = "Failed to download from URL: {$url}";
        if ($statusCode > 0) {
            $message .= " (HTTP {$statusCode})";
        }
        return new self($message);
    }
}
