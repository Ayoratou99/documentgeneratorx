<?php

namespace Ayoratoumvone\Documentgeneratorx\Parser;

use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;

class VariableParser
{
    /**
     * Parse variable syntax: {{varname:type,options}}
     * Examples:
     * - {{name:text}}
     * - {{age:number}}
     * - {{logo:image,width:200,height:100}}
     * - {{photo:image,ratio:16:9}}
     */
    public function parse(string $template): array
    {
        $variables = [];
        
        // Match pattern: {{varname:type,option1:value1,option2:value2}}
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        
        foreach ($matches[1] as $match) {
            $parsed = $this->parseVariable($match);
            $variables[$parsed['name']] = $parsed;
        }
        
        return $variables;
    }

    /**
     * Parse individual variable
     */
    protected function parseVariable(string $variableString): array
    {
        $parts = array_map('trim', explode(',', $variableString));
        
        // First part contains name and type
        $nameParts = explode(':', $parts[0]);
        $name = trim($nameParts[0]);
        $type = isset($nameParts[1]) ? trim($nameParts[1]) : 'text';
        
        $options = [];
        
        // Parse remaining options
        for ($i = 1; $i < count($parts); $i++) {
            if (strpos($parts[$i], ':') !== false) {
                [$key, $value] = explode(':', $parts[$i], 2);
                $options[trim($key)] = trim($value);
            }
        }
        
        return [
            'name' => $name,
            'type' => $type,
            'options' => $options,
            'original' => '{{' . $variableString . '}}',
        ];
    }

    /**
     * Validate variable value against type
     */
    public function validateValue(array $variableInfo, mixed $value): bool
    {
        return match ($variableInfo['type']) {
            'text', 'string' => is_string($value) || is_numeric($value),
            'number', 'integer', 'int' => is_numeric($value),
            'image' => $this->isValidImage($value),
            'date' => $value instanceof \DateTimeInterface || is_string($value),
            'boolean', 'bool' => is_bool($value),
            default => true,
        };
    }

    /**
     * Check if value is a valid image (file path or URL)
     */
    protected function isValidImage(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        // Check if it's a URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }
        
        // Check if it's a file path
        if (file_exists($value)) {
            $imageInfo = @getimagesize($value);
            return $imageInfo !== false;
        }
        
        return false;
    }

    /**
     * Format value according to type
     */
    public function formatValue(array $variableInfo, mixed $value): mixed
    {
        return match ($variableInfo['type']) {
            'number', 'integer', 'int' => (int) $value,
            'boolean', 'bool' => (bool) $value,
            'date' => $this->formatDate($value),
            'text', 'string' => (string) $value,
            'image' => $value, // Keep as is for image processing
            default => $value,
        };
    }

    /**
     * Format date value
     */
    protected function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        
        return (string) $value;
    }

    /**
     * Extract image dimensions from options
     */
    public function getImageDimensions(array $options): array
    {
        $dimensions = [
            'width' => null,
            'height' => null,
        ];
        
        if (isset($options['width'])) {
            $dimensions['width'] = (int) $options['width'];
        }
        
        if (isset($options['height'])) {
            $dimensions['height'] = (int) $options['height'];
        }
        
        // Handle ratio (e.g., ratio:16:9)
        if (isset($options['ratio'])) {
            $ratio = explode(':', $options['ratio']);
            if (count($ratio) === 2) {
                $dimensions['ratio'] = [
                    'width' => (int) $ratio[0],
                    'height' => (int) $ratio[1],
                ];
            }
        }
        
        return $dimensions;
    }
}