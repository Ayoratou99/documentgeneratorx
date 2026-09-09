<?php

/**
 * Demo: conditional blocks ({{if}} / {{elseif}} / {{else}} / {{endif}}).
 *
 * Builds a membership-card template with a block conditional, then renders it
 * three times (gold / silver / bronze) to:
 *   - examples/conditional_demo_template.docx  (the input template)
 *   - examples/conditional_demo_<tier>.pdf     (one PDF per membership tier)
 *
 * Run from the repo root:  php examples/conditional_demo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;

$dir          = __DIR__;
$templatePath = $dir . '/conditional_demo_template.docx';

// --- 1. Build the template .docx (each marker alone on its own line) --------
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Membership Card', ['bold' => true, 'size' => 16]);
$section->addTextBreak(1);
$section->addText('Holder: {{name:text}}');
$section->addTextBreak(1);

$section->addText('{{if:membership=gold}}');
$section->addText('GOLD member — premium lounge, priority support, free upgrades.');
$section->addText('{{elseif:membership=silver}}');
$section->addText('SILVER member — extended benefits and priority support.');
$section->addText('{{else}}');
$section->addText('STANDARD member — thank you for being with us.');
$section->addText('{{endif}}');

IOFactory::createWriter($phpWord, 'Word2007')->save($templatePath);
echo "Template written: {$templatePath}\n";

// --- 2. Render one PDF per tier --------------------------------------------
$generator = new DocxToPdfGenerator();

$people = [
    'gold'   => 'Alice Gold',
    'silver' => 'Bob Silver',
    'bronze' => 'Cara Bronze', // no matching branch -> {{else}}
];

foreach ($people as $tier => $name) {
    $out = $dir . "/conditional_demo_{$tier}.pdf";
    try {
        $generator->generate($templatePath, ['membership' => $tier, 'name' => $name], $out);
        echo "  {$tier}: {$out}\n";
    } catch (\Throwable $e) {
        echo "  {$tier}: PDF skipped (LibreOffice?): " . $e->getMessage() . "\n";
    }
}

echo "\nDone. Each PDF shows only the branch matching its membership tier.\n";
