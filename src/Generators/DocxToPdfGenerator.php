<?php

namespace Ayoratoumvone\Documentgeneratorx\Generators;

use Ayoratoumvone\Documentgeneratorx\Contracts\GeneratorInterface;
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;
use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use Ayoratoumvone\Documentgeneratorx\Processors\ImageProcessor;
use PhpOffice\PhpWord\IOFactory;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

/**
 * Generator that converts DOCX templates to PDF
 * 
 * Supports {{variable:type,options}} syntax
 */
class DocxToPdfGenerator implements GeneratorInterface
{
    protected VariableParser $parser;
    protected ImageProcessor $imageProcessor;

    public function __construct()
    {
        $this->parser = new VariableParser();
        $this->imageProcessor = new ImageProcessor();
    }

    /**
     * Generate PDF document from DOCX template
     */
    public function generate(string $templatePath, array $variables, string $outputPath): void
    {
        try {
            // Process the DOCX template with variables
            $processedDocxPath = $this->processDocxTemplate($templatePath, $variables);
            
            // Convert to PDF
            $this->convertDocxToPdf($processedDocxPath, $outputPath);
            
            // Clean up temporary DOCX
            @unlink($processedDocxPath);
            
        } catch (\Exception $e) {
            throw new DocumentGeneratorException(
                "Failed to generate PDF from DOCX template: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Process DOCX template with variables using direct XML manipulation
     */
    protected function processDocxTemplate(string $templatePath, array $variables): string
    {
        // Create a copy of the template
        $tempDocxPath = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        copy($templatePath, $tempDocxPath);
        
        // Open the DOCX as a ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($tempDocxPath) !== true) {
            throw new DocumentGeneratorException("Failed to open DOCX template");
        }
        
        // Read document.xml content
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new DocumentGeneratorException("Failed to read document.xml from DOCX");
        }
        
        // Fix fragmented XML: Word sometimes splits {{variable}} across multiple XML tags
        $documentXml = $this->fixFragmentedPlaceholders($documentXml);
        
        // Parse variables from the document
        $templateVariables = $this->parser->parse($documentXml);
        
        // Process each variable
        foreach ($variables as $key => $value) {
            $variableInfo = $templateVariables[$key] ?? null;
            
            if ($variableInfo) {
                // Skip images (handled separately)
                if ($variableInfo['type'] === 'image') {
                    continue;
                }
                // Replace with type-aware processing
                $replacement = $this->getReplacementValue($variableInfo, $value);
                $documentXml = str_replace($variableInfo['original'], $replacement, $documentXml);
            } else {
                // Simple replacement for variables without type info
                $documentXml = str_replace('{{' . $key . '}}', $this->formatValue($value), $documentXml);
            }
        }
        
        // Handle images separately (they need special XML structure)
        $documentXml = $this->processImages($documentXml, $variables, $templateVariables, $zip);
        
        // Save the modified document.xml back to the ZIP
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
        
        return $tempDocxPath;
    }

    /**
     * Fix fragmented placeholders in Word XML
     * Word sometimes splits {{variable:type}} across multiple <w:t> tags
     */
    protected function fixFragmentedPlaceholders(string $xml): string
    {
        // Extract all text content, find placeholders, then rebuild
        // This handles cases where {{ and }} are split across tags
        
        $maxIterations = 20;
        $iteration = 0;
        
        // Keep merging until no more fragmented placeholders
        while ($iteration < $maxIterations) {
            $changed = false;
            
            // Pattern: {{ followed by any XML tags, then more text, until }}
            // This handles: {{</w:t>...</w:t>name:</w:t>...</w:t>text</w:t>...</w:t>}}
            $xml = preg_replace_callback(
                '/\{\{((?:[^}]|(?:<[^>]+>))*?)\}\}/s',
                function ($matches) use (&$changed) {
                    $content = $matches[1];
                    // Remove all XML tags from inside the placeholder
                    $cleanContent = preg_replace('/<[^>]+>/', '', $content);
                    $changed = ($content !== $cleanContent);
                    return '{{' . $cleanContent . '}}';
                },
                $xml
            );
            
            // Also find incomplete {{ that needs merging with next w:t
            // Pattern: text with {{ but no }}, followed by </w:t>, then eventually }}
            $pattern = '/(<w:t[^>]*>)([^<]*\{\{[^}<]*)(<\/w:t>.*?<w:t[^>]*>)([^<]*\}\})/s';
            $newXml = preg_replace_callback(
                $pattern,
                function ($matches) use (&$changed) {
                    $changed = true;
                    // Extract the placeholder parts and merge them
                    $beforeTag = $matches[1];
                    $part1 = $matches[2]; // Contains {{
                    $middleTags = $matches[3];
                    $part2 = $matches[4]; // Contains }}
                    
                    // Extract text from middle tags
                    preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $middleTags, $textMatches);
                    $middleText = implode('', $textMatches[1] ?? []);
                    
                    return $beforeTag . $part1 . $middleText . $part2 . '</w:t>';
                },
                $xml
            );
            
            if ($newXml !== null && $newXml !== $xml) {
                $xml = $newXml;
                $changed = true;
            }
            
            if (!$changed) {
                break;
            }
            
            $iteration++;
        }
        
        return $xml;
    }

    /**
     * Get replacement value based on variable type
     */
    protected function getReplacementValue(array $variableInfo, mixed $value): string
    {
        // For images, return empty string (handled separately)
        if ($variableInfo['type'] === 'image') {
            return ''; // Will be handled by processImages
        }
        
        return match ($variableInfo['type']) {
            'text', 'string' => htmlspecialchars((string) $value, ENT_XML1),
            'number', 'integer', 'int' => (string) (int) $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value,
            'boolean', 'bool' => $value ? 'Yes' : 'No',
            default => htmlspecialchars((string) $value, ENT_XML1),
        };
    }

    /**
     * Process image variables
     */
    protected function processImages(string $documentXml, array $variables, array $templateVariables, ZipArchive $zip): string
    {
        foreach ($templateVariables as $key => $info) {
            if ($info['type'] !== 'image') {
                continue;
            }
            
            if (!isset($variables[$key])) {
                // Remove image placeholder if no value provided
                $documentXml = str_replace($info['original'], '', $documentXml);
                continue;
            }
            
            try {
                $imageSource = $variables[$key];
                $dimensions = $this->parser->getImageDimensions($info['options']);
                $processedImage = $this->imageProcessor->process($imageSource, $dimensions);
                
                // Add image to the DOCX package
                $imageData = file_get_contents($processedImage['path']);
                $imageExt = pathinfo($processedImage['path'], PATHINFO_EXTENSION) ?: 'png';
                $imageFileName = 'image_' . $key . '.' . $imageExt;
                
                // Add to media folder
                $zip->addFromString('word/media/' . $imageFileName, $imageData);
                
                // Create relationship for the image
                $relsContent = $zip->getFromName('word/_rels/document.xml.rels');
                $rId = 'rId' . (substr_count($relsContent, 'Relationship') + 1);
                
                $newRel = '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $imageFileName . '"/>';
                $relsContent = str_replace('</Relationships>', $newRel . '</Relationships>', $relsContent);
                
                $zip->deleteName('word/_rels/document.xml.rels');
                $zip->addFromString('word/_rels/document.xml.rels', $relsContent);
                
                // Calculate dimensions in EMUs (English Metric Units)
                $width = $processedImage['width'] ?? 200;
                $height = $processedImage['height'] ?? 100;
                $widthEmu = $width * 9525; // 1 pixel = 9525 EMUs
                $heightEmu = $height * 9525;
                
                // Create the image XML
                $imageXml = $this->createImageXml($rId, $widthEmu, $heightEmu, $key);
                
                // Replace the placeholder with the image XML
                $documentXml = str_replace($info['original'], $imageXml, $documentXml);
                
                // Clean up temp file
                if (str_starts_with(basename($processedImage['path']), 'resized_') ||
                    str_starts_with(basename($processedImage['path']), 'img_')) {
                    @unlink($processedImage['path']);
                }
                
            } catch (\Exception $e) {
                // If image processing fails, just remove the placeholder
                $documentXml = str_replace($info['original'], '[Image Error: ' . $e->getMessage() . ']', $documentXml);
            }
        }
        
        return $documentXml;
    }

    /**
     * Create image XML for DOCX
     */
    protected function createImageXml(string $rId, int $widthEmu, int $heightEmu, string $name): string
    {
        return '</w:t></w:r></w:p><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $widthEmu . '" cy="' . $heightEmu . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/><wp:docPr id="1" name="' . $name . '"/><wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="' . $name . '"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="' . $rId . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p><w:p><w:r><w:t>';
    }

    /**
     * Convert DOCX to PDF
     */
    protected function convertDocxToPdf(string $docxPath, string $pdfPath): void
    {
        // Load the DOCX file
        $phpWord = IOFactory::load($docxPath);
        
        // Convert to HTML first
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        
        // Save HTML to temp file
        $tempHtmlPath = tempnam(sys_get_temp_dir(), 'html_') . '.html';
        $htmlWriter->save($tempHtmlPath);
        
        // Read HTML content
        $htmlContent = file_get_contents($tempHtmlPath);
        
        // Add proper styling for PDF
        $htmlContent = $this->enhanceHtmlForPdf($htmlContent);
        
        // Convert HTML to PDF using Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Save PDF
        file_put_contents($pdfPath, $dompdf->output());
        
        // Clean up temp HTML
        @unlink($tempHtmlPath);
    }

    /**
     * Enhance HTML for better PDF rendering
     */
    protected function enhanceHtmlForPdf(string $html): string
    {
        $css = '<style>
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.6;
                margin: 40px;
            }
            img {
                max-width: 100%;
                height: auto;
                display: block;
                margin: 10px auto;
            }
            p {
                margin: 10px 0;
            }
            .centered {
                text-align: center;
            }
        </style>';
        
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $css . '</head>', $html);
        } else {
            $html = $css . $html;
        }
        
        return $html;
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

        return htmlspecialchars((string) $value, ENT_XML1);
    }
}
