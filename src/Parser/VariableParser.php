<?php

namespace Ayoratoumvone\Documentgeneratorx\Parser;

use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;

class VariableParser
{
    /**
     * Supported style properties
     */
    protected array $styleProperties = [
        'font-size',
        'font-weight',
        'font-style',
        'font-family',
        'color',
        'background-color',
        'text-decoration',
        'text-align',
        'underline',
        'bold',
        'italic',
    ];

    /**
     * Parse variable syntax: {{varname:type,options}}
     * Examples:
     * - {{name:text}}
     * - {{age:number}}
     * - {{logo:image,width:200,height:100}}
     * - {{photo:image,ratio:16:9}}
     * - {{title:text,font-size:18,bold:true,color:#333}}
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
        $styles = [];
        
        // Parse remaining options
        for ($i = 1; $i < count($parts); $i++) {
            if (strpos($parts[$i], ':') !== false) {
                [$key, $value] = explode(':', $parts[$i], 2);
                $key = trim($key);
                $value = trim($value);
                
                // Check if it's a style property
                if ($this->isStyleProperty($key)) {
                    $styles[$key] = $this->normalizeStyleValue($key, $value);
                } else {
                    $options[$key] = $value;
                }
            }
        }
        
        return [
            'name' => $name,
            'type' => $type,
            'options' => $options,
            'styles' => $styles,
            'original' => '{{' . $variableString . '}}',
        ];
    }

    /**
     * Get a regex pattern to match this placeholder with flexible whitespace.
     * Handles both {{nom:text}} and {{ nom : text }} formats.
     */
    public function getPlaceholderPattern(array $variableInfo): string
    {
        $name = preg_quote($variableInfo['name'], '/');
        $type = preg_quote($variableInfo['type'], '/');

        // Match {{ with optional whitespace, name, :, type, optional options, }}
        return '/\{\{\s*' . $name . '\s*:\s*' . $type
            . '(?:\s*,\s*[^}]*)?\s*\}\}/u';
    }

    /**
     * Check if a key is a style property
     */
    protected function isStyleProperty(string $key): bool
    {
        return in_array($key, $this->styleProperties);
    }

    /**
     * Normalize style value (convert shortcuts to CSS)
     */
    protected function normalizeStyleValue(string $property, string $value): string
    {
        // Handle boolean shortcuts
        if ($property === 'bold') {
            return $value === 'true' || $value === '1' ? 'bold' : 'normal';
        }
        
        if ($property === 'italic') {
            return $value === 'true' || $value === '1' ? 'italic' : 'normal';
        }
        
        if ($property === 'underline') {
            return $value === 'true' || $value === '1' ? 'underline' : 'none';
        }
        
        // Handle font-size without unit
        if ($property === 'font-size' && is_numeric($value)) {
            return $value . 'pt';
        }
        
        return $value;
    }

    /**
     * Convert styles array to CSS string
     */
    public function stylesToCss(array $styles): string
    {
        if (empty($styles)) {
            return '';
        }
        
        $css = [];
        
        foreach ($styles as $property => $value) {
            // Map shortcut properties to CSS
            $cssProperty = match ($property) {
                'bold' => 'font-weight',
                'italic' => 'font-style',
                'underline' => 'text-decoration',
                default => $property,
            };
            
            $css[] = "{$cssProperty}: {$value}";
        }
        
        return implode('; ', $css);
    }

    /**
     * Convert styles to DOCX XML run properties
     */
    public function stylesToDocxXml(array $styles): string
    {
        if (empty($styles)) {
            return '';
        }
        
        $props = [];
        
        foreach ($styles as $property => $value) {
            switch ($property) {
                case 'bold':
                    if ($value === 'bold') {
                        $props[] = '<w:b/>';
                    }
                    break;
                    
                case 'italic':
                    if ($value === 'italic') {
                        $props[] = '<w:i/>';
                    }
                    break;
                    
                case 'underline':
                    if ($value === 'underline') {
                        $props[] = '<w:u w:val="single"/>';
                    }
                    break;
                    
                case 'font-weight':
                    if ($value === 'bold') {
                        $props[] = '<w:b/>';
                    }
                    break;
                    
                case 'font-style':
                    if ($value === 'italic') {
                        $props[] = '<w:i/>';
                    }
                    break;
                    
                case 'text-decoration':
                    if ($value === 'underline') {
                        $props[] = '<w:u w:val="single"/>';
                    } elseif ($value === 'line-through') {
                        $props[] = '<w:strike/>';
                    }
                    break;
                    
                case 'font-size':
                    // Convert pt to half-points (DOCX uses half-points)
                    $size = (int) preg_replace('/[^0-9]/', '', $value);
                    $halfPoints = $size * 2;
                    $props[] = "<w:sz w:val=\"{$halfPoints}\"/>";
                    $props[] = "<w:szCs w:val=\"{$halfPoints}\"/>";
                    break;
                    
                case 'color':
                    $color = ltrim($value, '#');
                    // Handle named colors
                    $color = $this->namedColorToHex($color);
                    $props[] = "<w:color w:val=\"{$color}\"/>";
                    break;
                    
                case 'background-color':
                    $color = ltrim($value, '#');
                    $color = $this->namedColorToHex($color);
                    $props[] = "<w:shd w:val=\"clear\" w:fill=\"{$color}\"/>";
                    break;
                    
                case 'font-family':
                    $props[] = "<w:rFonts w:ascii=\"{$value}\" w:hAnsi=\"{$value}\"/>";
                    break;
            }
        }
        
        if (empty($props)) {
            return '';
        }
        
        return '<w:rPr>' . implode('', $props) . '</w:rPr>';
    }

    /**
     * Convert named color to hex
     */
    protected function namedColorToHex(string $color): string
    {
        $colors = [
            'red' => 'FF0000',
            'green' => '00FF00',
            'blue' => '0000FF',
            'black' => '000000',
            'white' => 'FFFFFF',
            'yellow' => 'FFFF00',
            'orange' => 'FFA500',
            'purple' => '800080',
            'pink' => 'FFC0CB',
            'gray' => '808080',
            'grey' => '808080',
            'brown' => 'A52A2A',
            'navy' => '000080',
            'teal' => '008080',
            'maroon' => '800000',
        ];
        
        return $colors[strtolower($color)] ?? $color;
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

    /**
     * Check if variable has styles
     */
    public function hasStyles(array $variableInfo): bool
    {
        return !empty($variableInfo['styles'] ?? []);
    }
}
