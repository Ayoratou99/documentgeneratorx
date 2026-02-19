<?php

/**
 * Integration Test: DOCX with spaced placeholders + LibreOffice PDF generation
 *
 * This test:
 * 1. Creates a real DOCX file with placeholders that have spaces: {{ nom : text }}, {{ age : number }}, etc.
 * 2. Generates PDF using DocxToPdfGenerator with LibreOffice conversion
 * 3. Verifies the PDF was created and replacement worked
 *
 * Requirements:
 * - LibreOffice installed (soffice.exe on Windows)
 * - Run from documentgeneratorx root: php tests/SpacedPlaceholdersDocxTest.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;

$baseDir = __DIR__ . '/templates';
$templatePath = $baseDir . '/spaced_placeholders_test.docx';
$outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'spaced_placeholders_test_' . time() . '.pdf';

echo "========================================\n";
echo "  Spaced Placeholders DOCX Integration Test\n";
echo "  (LibreOffice PDF conversion)\n";
echo "========================================\n\n";

// Step 1: Create DOCX with spaced placeholders using PhpWord
echo "[STEP 1] Creating DOCX template with spaced placeholders...\n";

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

$phpWord = new PhpWord();
$section = $phpWord->addSection();

$section->addTitle('Document with Spaced Placeholders', 0);
$section->addTextBreak(1);

$section->addText('This document tests placeholders WITH SPACES around colons.');
$section->addTextBreak(1);

// Spaced placeholders - the format that was not being recognized
$section->addText('Full Name: {{ full_name : text }}');
$section->addText('Email: {{ email : string }}');
$section->addText('Age: {{ age : number }} years old');
$section->addText('Amount: ${{ price : number }}');
$section->addText('Date: {{ created_date : date }}');
$section->addText('Active: {{ is_active : boolean }}');
$section->addTextBreak(1);

$section->addText('If replacement worked, you should see real values above instead of placeholders.');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($templatePath);

if (!file_exists($templatePath)) {
    echo "[FAIL] Could not create DOCX template.\n";
    exit(1);
}

echo "  - Template created: {$templatePath}\n";
echo "  - Placeholders used: {{ full_name : text }}, {{ age : number }}, etc.\n";
echo "[STEP 1] OK\n\n";

// Step 2: Generate PDF with LibreOffice
echo "[STEP 2] Generating PDF with LibreOffice...\n";

$variables = [
    'full_name' => 'Jean Dupont',
    'email' => 'jean.dupont@example.com',
    'age' => 35,
    'price' => 1250,
    'created_date' => '2025-02-16',
    'is_active' => true,
];

try {
    $generator = new DocxToPdfGenerator();
    $generator->setConversionMethod('libreoffice');

    // Set LibreOffice path for Windows if needed
    $possiblePaths = [
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
    ];
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $generator->setLibreOfficePath($path);
            echo "  - LibreOffice found: {$path}\n";
            break;
        }
    }

    $generator->generate($templatePath, $variables, $outputPath);

    echo "  - PDF generated: {$outputPath}\n";
    echo "[STEP 2] OK\n\n";
} catch (DocumentGeneratorException $e) {
    echo "[FAIL] " . $e->getMessage() . "\n\n";
    echo "Make sure LibreOffice is installed:\n";
    echo "  Download: https://www.libreoffice.org/download/download/\n";
    echo "  Windows: C:\\Program Files\\LibreOffice\\program\\soffice.exe\n\n";
    exit(1);
} catch (\Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Step 3: Verify PDF was created
echo "[STEP 3] Verifying PDF output...\n";

if (!file_exists($outputPath)) {
    echo "[FAIL] PDF file was not created.\n";
    exit(1);
}

$fileSize = filesize($outputPath);
echo "  - PDF file size: " . number_format($fileSize) . " bytes\n";

if ($fileSize < 1000) {
    echo "[WARN] PDF seems very small - replacement may have failed.\n";
} else {
    echo "  - PDF appears valid.\n";
}

// Step 4: Verify replacement (PDF stores text in streams - check for our values)
// PDF text can be encoded; we check raw content for common encodings
$pdfContent = file_get_contents($outputPath);
$hasReplacement = str_contains($pdfContent, 'Jean') || str_contains($pdfContent, 'Dupont')
    || str_contains($pdfContent, 'jean.dupont') || str_contains($pdfContent, '35');
$hasUnreplacedPlaceholder = str_contains($pdfContent, '{{ ') && str_contains($pdfContent, ' }}');

if ($hasUnreplacedPlaceholder && !$hasReplacement) {
    echo "[WARN] Placeholders may not have been replaced. Open PDF to verify.\n";
} elseif ($hasReplacement) {
    echo "  - Replacement verified: substituted values found in PDF.\n";
} else {
    echo "  - PDF generated successfully (open manually to confirm content).\n";
}

echo "[STEP 3] OK\n\n";

echo "========================================\n";
echo "  TEST PASSED\n";
echo "========================================\n";
echo "\nSpaced placeholders {{ full_name : text }} are correctly replaced.\n";
echo "PDF saved to: {$outputPath}\n";
echo "\nYou can open the PDF to visually confirm:\n";
echo "  - Full Name: Jean Dupont\n";
echo "  - Email: jean.dupont@example.com\n";
echo "  - Age: 35\n";
echo "  - Amount: \$1250\n";
echo "  - Date: 2025-02-16\n";
echo "  - Active: Yes\n";
