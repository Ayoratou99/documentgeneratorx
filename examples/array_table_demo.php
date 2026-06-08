<?php

/**
 * Demo: array variables -> repeating table rows.
 *
 * Builds a real Word template (4-column table with array placeholders +
 * spare blank rows), then runs the generator to produce:
 *   - examples/array_demo_template.docx  (the input template)
 *   - examples/array_demo_result.docx    (rows expanded, open in Word)
 *   - examples/array_demo_result.pdf     (final PDF, needs LibreOffice)
 *
 * Run from the repo root:  php examples/array_table_demo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;

$dir          = __DIR__;
$templatePath = $dir . '/array_demo_template.docx';
$resultDocx   = $dir . '/array_demo_result.docx';
$resultPdf    = $dir . '/array_demo_result.pdf';

// --- 1. Build the template .docx ------------------------------------------
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Description des objets :', ['bold' => true, 'size' => 14]);
$section->addTextBreak(1);

$table = $section->addTable([
    'borderSize'  => 6,
    'borderColor' => '000000',
    'cellMargin'  => 80,
]);

// Header row
$table->addRow();
foreach (['Numero', 'Nom', 'Q', 'Dimission'] as $label) {
    $table->addCell(2200)->addText($label, ['bold' => true]);
}

// The ONE placeholder row (this is the template row that gets cloned)
$table->addRow();
foreach (['nums', 'noms', 'qs', 'dims'] as $name) {
    $table->addCell(2200)->addText('{{' . $name . ':array}}');
}

// Two spare blank rows drawn by the author (as in the screenshot)
for ($r = 0; $r < 2; $r++) {
    $table->addRow();
    for ($c = 0; $c < 4; $c++) {
        $table->addCell(2200)->addText('');
    }
}

IOFactory::createWriter($phpWord, 'Word2007')->save($templatePath);
echo "Template written: {$templatePath}\n";

// --- 2. The data (6 items > 2 drawn rows, so rows auto-add) ----------------
$variables = [
    'nums' => ['1', '2', '3', '4', '5', '6'],
    'noms' => ['Marteau', 'Scie', 'Clou', 'Vis', 'Pince', 'Tournevis'],
    'qs'   => ['10', '5', '200', '500', '8', '12'],
    'dims' => ['20cm', '40cm', '3cm', '2cm', '15cm', '18cm'],
];

$generator = new DocxToPdfGenerator();

// --- 3. Produce the expanded .docx (openable in Word) ----------------------
$ref = new ReflectionMethod($generator, 'processDocxTemplate');
$ref->setAccessible(true);
$expanded = $ref->invoke($generator, $templatePath, $variables);
copy($expanded, $resultDocx);
@unlink($expanded);
echo "Expanded DOCX: {$resultDocx}\n";

// --- 4. Produce the final PDF (requires LibreOffice) -----------------------
try {
    $generator->generate($templatePath, $variables, $resultPdf);
    echo "Final PDF:     {$resultPdf}\n";
} catch (\Throwable $e) {
    echo "PDF skipped (LibreOffice not available): " . $e->getMessage() . "\n";
}

echo "\nDone. Open the DOCX/PDF above to see the 6-row table.\n";
