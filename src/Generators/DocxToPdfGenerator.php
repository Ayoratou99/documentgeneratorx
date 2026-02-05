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
 * Supports two conversion methods:
 * 1. LibreOffice (default, recommended) - Direct conversion, preserves formatting
 * 2. Dompdf (HTML-based fallback) - Use when LibreOffice is not available
 * 
 * Supports {{variable:type,options}} syntax with styling
 */
class DocxToPdfGenerator implements GeneratorInterface
{
    protected VariableParser $parser;
    protected ImageProcessor $imageProcessor;
    protected ?string $libreOfficePath = null;
    protected string $conversionMethod = 'libreoffice';

    public function __construct()
    {
        $this->parser = new VariableParser();
        $this->imageProcessor = new ImageProcessor();
        
        // Load config if available (Laravel)
        try {
            if (function_exists('app') && app()->bound('config')) {
                $this->conversionMethod = config('documentgenerator.pdf_conversion', 'libreoffice');
                $this->libreOfficePath = config('documentgenerator.libreoffice_path');
            }
        } catch (\Throwable $e) {
            // Running outside Laravel - use defaults (libreoffice)
        }
    }

    /**
     * Set conversion method ('libreoffice', 'dompdf', or 'auto')
     */
    public function setConversionMethod(string $method): self
    {
        $this->conversionMethod = $method;
        return $this;
    }

    /**
     * Set LibreOffice executable path
     */
    public function setLibreOfficePath(string $path): self
    {
        $this->libreOfficePath = $path;
        return $this;
    }

    /**
     * Generate PDF document from DOCX template
     */
    public function generate(string $templatePath, array $variables, string $outputPath): void
    {
        try {
            // Process the DOCX template with variables
            $processedDocxPath = $this->processDocxTemplate($templatePath, $variables);
            
            // Convert to PDF using configured method
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
                // Replace with type-aware processing and styles
                $replacement = $this->getStyledReplacementValue($variableInfo, $value);
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
     * Get styled replacement value with DOCX XML formatting
     */
    protected function getStyledReplacementValue(array $variableInfo, mixed $value): string
    {
        $text = $this->getReplacementValue($variableInfo, $value);
        
        // Check if styles are defined
        $styles = $variableInfo['styles'] ?? [];
        
        if (empty($styles)) {
            return $text;
        }
        
        // Generate styled XML run
        $styleXml = $this->parser->stylesToDocxXml($styles);
        
        // Wrap text in a run with style properties
        // We need to close the current text run and create a new styled one
        return '</w:t></w:r><w:r>' . $styleXml . '<w:t>' . $text . '</w:t></w:r><w:r><w:t>';
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
     * Convert DOCX to PDF using configured method
     */
    protected function convertDocxToPdf(string $docxPath, string $pdfPath): void
    {
        $method = $this->conversionMethod;
        
        // LibreOffice is the default - verify it's available
        if ($method === 'libreoffice' && !$this->detectLibreOffice()) {
            throw new DocumentGeneratorException(
                "LibreOffice is not installed or not found on this system.\n\n" .
                "To fix this, you have two options:\n\n" .
                "1. Install LibreOffice (recommended for best PDF quality):\n" .
                "   Download from: https://www.libreoffice.org/download/download/\n" .
                "   Then set the path in your .env file:\n" .
                "   LIBREOFFICE_PATH=\"C:\\Program Files\\LibreOffice\\program\\soffice.exe\"\n\n" .
                "2. Use HTML-based conversion (no LibreOffice required):\n" .
                "   Set in your .env file:\n" .
                "   DOCUMENT_PDF_CONVERSION=dompdf\n\n" .
                "Note: The HTML-based conversion may not preserve all formatting from the original document."
            );
        }
        
        if ($method === 'libreoffice') {
            $this->convertWithLibreOffice($docxPath, $pdfPath);
        } else {
            $this->convertWithDompdf($docxPath, $pdfPath);
        }
    }

    /**
     * Convert DOCX to PDF using LibreOffice (best quality)
     */
    protected function convertWithLibreOffice(string $docxPath, string $pdfPath): void
    {
        $libreOffice = $this->getLibreOfficePath();
        
        if (!$libreOffice) {
            throw new DocumentGeneratorException(
                'LibreOffice not found. Install LibreOffice or set the path in config.'
            );
        }
        
        // Create output directory
        $outputDir = dirname($pdfPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Use a temp directory for LibreOffice output
        $tempDir = sys_get_temp_dir();
        
        // Build the command
        $command = sprintf(
            '"%s" --headless --convert-to pdf --outdir "%s" "%s"',
            $libreOffice,
            $tempDir,
            $docxPath
        );
        
        // Execute LibreOffice conversion
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new DocumentGeneratorException(
                'LibreOffice conversion failed: ' . implode("\n", $output)
            );
        }
        
        // LibreOffice creates the PDF with same name as input
        $generatedPdf = $tempDir . DIRECTORY_SEPARATOR . 
                        pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
        
        if (!file_exists($generatedPdf)) {
            throw new DocumentGeneratorException(
                'LibreOffice did not generate the PDF file'
            );
        }
        
        // Move to final destination
        rename($generatedPdf, $pdfPath);
    }

    /**
     * Convert DOCX to PDF using Dompdf (HTML conversion)
     */
    protected function convertWithDompdf(string $docxPath, string $pdfPath): void
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
     * Improves layout to better match original Word document
     */
    protected function enhanceHtmlForPdf(string $html): string
    {
        $css = '<style>
            /* Reset and base styles */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            @page {
                margin: 2.5cm 2cm 2.5cm 2cm;
            }
            
            body {
                font-family: "DejaVu Sans", "Calibri", "Arial", sans-serif;
                font-size: 11pt;
                line-height: 1.15;
                color: #000000;
            }
            
            /* Paragraphs - match Word default spacing */
            p {
                margin: 0 0 8pt 0;
                text-align: justify;
                orphans: 2;
                widows: 2;
            }
            
            /* Headings */
            h1, h2, h3, h4, h5, h6 {
                font-weight: bold;
                margin-top: 12pt;
                margin-bottom: 6pt;
                page-break-after: avoid;
            }
            
            h1 { font-size: 16pt; }
            h2 { font-size: 14pt; }
            h3 { font-size: 12pt; }
            h4 { font-size: 11pt; }
            
            /* Tables - preserve Word table styling */
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10pt 0;
                page-break-inside: avoid;
            }
            
            td, th {
                border: 1px solid #000000;
                padding: 5pt 8pt;
                vertical-align: top;
                text-align: left;
            }
            
            th {
                font-weight: bold;
                background-color: #f0f0f0;
            }
            
            /* Images */
            img {
                max-width: 100%;
                height: auto;
                display: block;
                margin: 8pt auto;
            }
            
            /* Lists */
            ul, ol {
                margin: 6pt 0 6pt 20pt;
                padding-left: 20pt;
            }
            
            li {
                margin-bottom: 3pt;
            }
            
            /* Text alignment classes */
            .text-left { text-align: left; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-justify { text-align: justify; }
            
            /* Font styles */
            strong, b { font-weight: bold; }
            em, i { font-style: italic; }
            u { text-decoration: underline; }
            s, strike { text-decoration: line-through; }
            
            /* Preserve whitespace in preformatted text */
            pre {
                font-family: "DejaVu Sans Mono", "Courier New", monospace;
                font-size: 10pt;
                white-space: pre-wrap;
                background-color: #f5f5f5;
                padding: 8pt;
                border: 1px solid #ddd;
            }
            
            /* Page breaks */
            .page-break {
                page-break-before: always;
            }
            
            /* Horizontal rule */
            hr {
                border: none;
                border-top: 1px solid #000000;
                margin: 12pt 0;
            }
            
            /* Links */
            a {
                color: #0563C1;
                text-decoration: underline;
            }
            
            /* Blockquote */
            blockquote {
                margin: 10pt 0 10pt 20pt;
                padding-left: 10pt;
                border-left: 3pt solid #ccc;
                font-style: italic;
            }
            
            /* Superscript and subscript */
            sup { font-size: 8pt; vertical-align: super; }
            sub { font-size: 8pt; vertical-align: sub; }
        </style>';
        
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $css . '</head>', $html);
        } else {
            $html = $css . $html;
        }
        
        // Fix common PhpWord HTML issues
        $html = $this->fixPhpWordHtmlIssues($html);
        
        return $html;
    }

    /**
     * Fix common issues in PhpWord generated HTML
     */
    protected function fixPhpWordHtmlIssues(string $html): string
    {
        // Remove empty paragraphs that create extra spacing
        $html = preg_replace('/<p[^>]*>\s*<\/p>/', '', $html);
        
        // Fix double spacing issues
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/', '<br><br>', $html);
        
        // Ensure images have proper dimensions if specified
        $html = preg_replace_callback(
            '/<img([^>]*)style="([^"]*)"([^>]*)>/i',
            function ($matches) {
                $style = $matches[2];
                // Ensure max-width doesn't break layout
                if (strpos($style, 'max-width') === false) {
                    $style .= '; max-width: 100%';
                }
                return '<img' . $matches[1] . 'style="' . $style . '"' . $matches[3] . '>';
            },
            $html
        );
        
        // Fix table width issues
        $html = preg_replace('/<table([^>]*)>/', '<table$1 style="width: 100%">', $html);
        
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

    /**
     * Detect if LibreOffice is available on the system
     */
    protected function detectLibreOffice(): bool
    {
        return $this->getLibreOfficePath() !== null;
    }

    /**
     * Get the LibreOffice executable path
     * 
     * If a path is explicitly configured (via setLibreOfficePath or config),
     * it will be validated and used. If the configured path doesn't exist,
     * null is returned (no fallback to auto-detection).
     * 
     * If no path is configured, auto-detection is used.
     */
    protected function getLibreOfficePath(): ?string
    {
        // If path is explicitly configured, validate and return it (no fallback)
        if ($this->libreOfficePath) {
            return file_exists($this->libreOfficePath) ? $this->libreOfficePath : null;
        }
        
        // Auto-detect: check common paths
        $paths = $this->getCommonLibreOfficePaths();
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Try to find using 'which' command on Unix or 'where' on Windows
        $command = PHP_OS_FAMILY === 'Windows' ? 'where soffice 2>nul' : 'which libreoffice 2>/dev/null || which soffice 2>/dev/null';
        $output = [];
        exec($command, $output);
        
        if (!empty($output[0]) && file_exists(trim($output[0]))) {
            return trim($output[0]);
        }
        
        return null;
    }

    /**
     * Get common LibreOffice installation paths
     */
    protected function getCommonLibreOfficePaths(): array
    {
        $paths = [];
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows paths
            $paths = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                getenv('LOCALAPPDATA') . '\\Programs\\LibreOffice\\program\\soffice.exe',
            ];
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // macOS paths
            $paths = [
                '/Applications/LibreOffice.app/Contents/MacOS/soffice',
                '/usr/local/bin/soffice',
            ];
        } else {
            // Linux paths
            $paths = [
                '/usr/bin/libreoffice',
                '/usr/bin/soffice',
                '/usr/local/bin/libreoffice',
                '/usr/local/bin/soffice',
                '/snap/bin/libreoffice',
            ];
        }
        
        return $paths;
    }

    /**
     * Check if LibreOffice is available
     */
    public function isLibreOfficeAvailable(): bool
    {
        return $this->detectLibreOffice();
    }

    /**
     * Get the current conversion method being used
     */
    public function getConversionMethod(): string
    {
        if ($this->conversionMethod === 'auto') {
            return $this->detectLibreOffice() ? 'libreoffice' : 'dompdf';
        }
        return $this->conversionMethod;
    }
}
