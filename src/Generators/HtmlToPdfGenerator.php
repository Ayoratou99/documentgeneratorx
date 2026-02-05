<?php

namespace Ayoratoumvone\Documentgeneratorx\Generators;

use Ayoratoumvone\Documentgeneratorx\Contracts\GeneratorInterface;
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;
use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use Ayoratoumvone\Documentgeneratorx\Processors\ImageProcessor;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generator that converts HTML templates to PDF
 */
class HtmlToPdfGenerator implements GeneratorInterface
{
    protected VariableParser $parser;
    protected ImageProcessor $imageProcessor;

    public function __construct()
    {
        $this->parser = new VariableParser();
        $this->imageProcessor = new ImageProcessor();
    }

    /**
     * Generate PDF document from HTML template
     */
    public function generate(string $templatePath, array $variables, string $outputPath): void
    {
        try {
            // Read template content
            $templateContent = file_get_contents($templatePath);
            
            if ($templateContent === false) {
                throw new DocumentGeneratorException("Failed to read template file");
            }
            
            // Parse variables from template
            $templateVariables = $this->parser->parse($templateContent);
            
            // Replace variables in content
            $processedContent = $this->replaceVariables($templateContent, $variables, $templateVariables);
            
            // Generate PDF
            $this->generatePdf($processedContent, $outputPath);
        } catch (\Exception $e) {
            throw new DocumentGeneratorException(
                "Failed to generate PDF document: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Replace variables in template content
     */
    protected function replaceVariables(string $content, array $variables, array $templateVariables): string
    {
        foreach ($variables as $key => $value) {
            $variableInfo = $templateVariables[$key] ?? null;
            
            if (!$variableInfo) {
                // Simple replacement
                $content = str_replace('{{' . $key . '}}', $this->formatValue($value), $content);
                continue;
            }
            
            // Validate value type
            if (!$this->parser->validateValue($variableInfo, $value)) {
                throw new DocumentGeneratorException(
                    "Invalid value type for variable '{$key}'. Expected: {$variableInfo['type']}"
                );
            }
            
            // Replace based on type
            $replacement = match ($variableInfo['type']) {
                'image' => $this->createImageHtml($value, $variableInfo['options']),
                'text', 'string' => $this->createStyledText($variableInfo, $value),
                'number', 'integer', 'int' => $this->createStyledText($variableInfo, $value),
                'date' => $this->createStyledText($variableInfo, $value),
                'boolean', 'bool' => $this->createStyledText($variableInfo, $value ? 'Yes' : 'No'),
                default => $this->formatValue($value),
            };
            
            $content = str_replace($variableInfo['original'], $replacement, $content);
        }
        
        return $content;
    }

    /**
     * Create styled text with inline CSS
     */
    protected function createStyledText(array $variableInfo, mixed $value): string
    {
        $text = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $styles = $variableInfo['styles'] ?? [];
        
        if (empty($styles)) {
            return $text;
        }
        
        // Convert styles to CSS
        $css = $this->parser->stylesToCss($styles);
        
        return '<span style="' . $css . '">' . $text . '</span>';
    }

    /**
     * Create HTML for image
     */
    protected function createImageHtml(string $imageSource, array $options): string
    {
        try {
            // Get dimensions
            $dimensions = $this->parser->getImageDimensions($options);
            
            // Process image
            $processedImage = $this->imageProcessor->process($imageSource, $dimensions);
            
            // Convert image to base64
            $imageData = base64_encode(file_get_contents($processedImage['path']));
            $imageInfo = getimagesize($processedImage['path']);
            $mimeType = $imageInfo['mime'];
            
            // Build HTML
            $style = ['display: block', 'margin: 0 auto']; // Center by default
            if ($processedImage['width']) {
                $style[] = "width: {$processedImage['width']}px";
            }
            if ($processedImage['height']) {
                $style[] = "height: {$processedImage['height']}px";
            }
            
            $styleAttr = ' style="' . implode('; ', $style) . '"';
            
            $html = sprintf(
                '<img src="data:%s;base64,%s"%s />',
                $mimeType,
                $imageData,
                $styleAttr
            );
            
            // Clean up temp file
            if (str_starts_with(basename($processedImage['path']), 'resized_') ||
                str_starts_with(basename($processedImage['path']), 'img_')) {
                @unlink($processedImage['path']);
            }
            
            return $html;
        } catch (\Exception $e) {
            throw new DocumentGeneratorException(
                "Failed to process image: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Generate PDF from HTML content
     */
    protected function generatePdf(string $htmlContent, string $outputPath): void
    {
        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        
        // Load HTML
        $dompdf->loadHtml($htmlContent);
        
        // Set paper size
        $dompdf->setPaper('A4', 'portrait');
        
        // Render PDF
        $dompdf->render();
        
        // Save to file
        file_put_contents($outputPath, $dompdf->output());
    }

    /**
     * Format value for output
     */
    protected function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
