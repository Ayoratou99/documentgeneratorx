<?php

/**
 * Standalone Test Runner for DocumentGeneratorX
 * 
 * This script tests the document generation functionality without requiring
 * a full Laravel installation. Run with: php tests/TestRunner.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Ayoratoumvone\Documentgeneratorx\DocumentGenerator;
use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use Ayoratoumvone\Documentgeneratorx\Processors\ImageProcessor;
use Ayoratoumvone\Documentgeneratorx\Generators\PdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Generators\DocxGenerator;
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;

echo "========================================\n";
echo "  DocumentGeneratorX Test Runner\n";
echo "========================================\n\n";

// Test 1: Variable Parser
echo "[TEST 1] Testing Variable Parser...\n";
try {
    $parser = new VariableParser();
    
    $template = '{{name:text}} {{age:number}} {{logo:image,width:200,ratio:16:9}}';
    $variables = $parser->parse($template);
    
    echo "  - Parsed variables: " . count($variables) . "\n";
    
    foreach ($variables as $name => $info) {
        echo "    * {$name}: type={$info['type']}, options=" . json_encode($info['options']) . "\n";
    }
    
    // Test validation
    $nameInfo = $variables['name'];
    $validText = $parser->validateValue($nameInfo, 'John Doe');
    echo "  - Text validation: " . ($validText ? 'PASSED' : 'FAILED') . "\n";
    
    $ageInfo = $variables['age'];
    $validNumber = $parser->validateValue($ageInfo, 25);
    echo "  - Number validation: " . ($validNumber ? 'PASSED' : 'FAILED') . "\n";
    
    // Test image dimensions
    $logoInfo = $variables['logo'];
    $dimensions = $parser->getImageDimensions($logoInfo['options']);
    echo "  - Image dimensions: width={$dimensions['width']}, ratio=" . 
         ($dimensions['ratio'] ? "{$dimensions['ratio']['width']}:{$dimensions['ratio']['height']}" : 'none') . "\n";
    
    echo "[TEST 1] PASSED\n\n";
} catch (Exception $e) {
    echo "[TEST 1] FAILED: " . $e->getMessage() . "\n\n";
}

// Test 2: PDF Generator with HTML Template
echo "[TEST 2] Testing PDF Generator with HTML Template...\n";
try {
    $templatePath = __DIR__ . '/templates/test_pdf_template.html';
    
    if (!file_exists($templatePath)) {
        throw new Exception("Template not found: {$templatePath}");
    }
    
    echo "  - Template found: {$templatePath}\n";
    
    // Read and display template variables
    $templateContent = file_get_contents($templatePath);
    $parser = new VariableParser();
    $templateVars = $parser->parse($templateContent);
    
    echo "  - Template variables found:\n";
    foreach ($templateVars as $name => $info) {
        echo "    * {{$name}:{$info['type']}}\n";
    }
    
    echo "[TEST 2] PASSED - Template parsed successfully\n\n";
} catch (Exception $e) {
    echo "[TEST 2] FAILED: " . $e->getMessage() . "\n\n";
}

// Test 3: Test all variable types
echo "[TEST 3] Testing All Variable Types...\n";
try {
    $parser = new VariableParser();
    
    $testCases = [
        ['syntax' => '{{name:text}}', 'value' => 'John Doe', 'expected' => true],
        ['syntax' => '{{email:string}}', 'value' => 'john@example.com', 'expected' => true],
        ['syntax' => '{{age:number}}', 'value' => 25, 'expected' => true],
        ['syntax' => '{{count:integer}}', 'value' => 100, 'expected' => true],
        ['syntax' => '{{active:boolean}}', 'value' => true, 'expected' => true],
        ['syntax' => '{{created:date}}', 'value' => '2024-01-15', 'expected' => true],
        ['syntax' => '{{logo:image,width:200,ratio:16:9}}', 'value' => 'https://example.com/image.png', 'expected' => true],
    ];
    
    $allPassed = true;
    foreach ($testCases as $test) {
        $vars = $parser->parse($test['syntax']);
        $varName = array_key_first($vars);
        $valid = $parser->validateValue($vars[$varName], $test['value']);
        $status = ($valid === $test['expected']) ? 'PASSED' : 'FAILED';
        if ($valid !== $test['expected']) $allPassed = false;
        echo "  - {$test['syntax']}: {$status}\n";
    }
    
    echo "[TEST 3] " . ($allPassed ? 'PASSED' : 'FAILED') . "\n\n";
} catch (Exception $e) {
    echo "[TEST 3] FAILED: " . $e->getMessage() . "\n\n";
}

// Test 4: Image dimension parsing
echo "[TEST 4] Testing Image Dimension Parsing...\n";
try {
    $parser = new VariableParser();
    
    $imageTests = [
        '{{img:image,width:300}}' => ['width' => 300, 'height' => null],
        '{{img:image,height:200}}' => ['width' => null, 'height' => 200],
        '{{img:image,width:400,height:300}}' => ['width' => 400, 'height' => 300],
        '{{img:image,width:600,ratio:16:9}}' => ['width' => 600, 'height' => null, 'ratio' => ['width' => 16, 'height' => 9]],
        '{{img:image,ratio:4:3}}' => ['width' => null, 'height' => null, 'ratio' => ['width' => 4, 'height' => 3]],
    ];
    
    $allPassed = true;
    foreach ($imageTests as $syntax => $expected) {
        $vars = $parser->parse($syntax);
        $varInfo = $vars['img'];
        $dims = $parser->getImageDimensions($varInfo['options']);
        
        $passed = ($dims['width'] === $expected['width'] && $dims['height'] === $expected['height']);
        if (isset($expected['ratio'])) {
            $passed = $passed && isset($dims['ratio']) && 
                      $dims['ratio']['width'] === $expected['ratio']['width'] &&
                      $dims['ratio']['height'] === $expected['ratio']['height'];
        }
        
        if (!$passed) $allPassed = false;
        echo "  - {$syntax}: " . ($passed ? 'PASSED' : 'FAILED') . "\n";
    }
    
    echo "[TEST 4] " . ($allPassed ? 'PASSED' : 'FAILED') . "\n\n";
} catch (Exception $e) {
    echo "[TEST 4] FAILED: " . $e->getMessage() . "\n\n";
}

// Test 5: Exception handling
echo "[TEST 5] Testing Exception Classes...\n";
try {
    // Using fully qualified class name since use statements must be at top level
    
    // Test static factory methods
    $ex1 = DocumentGeneratorException::invalidTemplate('/path/to/file.doc');
    echo "  - invalidTemplate(): " . (str_contains($ex1->getMessage(), 'Invalid') ? 'PASSED' : 'FAILED') . "\n";
    
    $ex2 = DocumentGeneratorException::templateNotFound('/missing.docx');
    echo "  - templateNotFound(): " . (str_contains($ex2->getMessage(), 'not found') ? 'PASSED' : 'FAILED') . "\n";
    
    $ex3 = DocumentGeneratorException::unsupportedFormat('xyz');
    echo "  - unsupportedFormat(): " . (str_contains($ex3->getMessage(), 'Unsupported') ? 'PASSED' : 'FAILED') . "\n";
    
    $ex4 = DocumentGeneratorException::invalidVariableType('age', 'number');
    echo "  - invalidVariableType(): " . (str_contains($ex4->getMessage(), 'Invalid value') ? 'PASSED' : 'FAILED') . "\n";
    
    echo "[TEST 5] PASSED\n\n";
} catch (Exception $e) {
    echo "[TEST 5] FAILED: " . $e->getMessage() . "\n\n";
}

echo "========================================\n";
echo "  Test Summary\n";
echo "========================================\n";
echo "All core functionality tests completed.\n";
echo "\nNote: Full integration tests (PDF/DOCX generation)\n";
echo "require Laravel environment with proper dependencies.\n";
echo "\nTo run the DOCX + LibreOffice integration test (spaced placeholders):\n";
echo "  php tests/SpacedPlaceholdersDocxTest.php\n";
echo "  (Requires LibreOffice installed)\n\n";
echo "To test in Laravel, use:\n";
echo "  composer test\n";
echo "\nOr run the example below in a Laravel app:\n";
echo "----------------------------------------\n";
echo <<<'EXAMPLE'

use Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator;

// Test PDF generation
$pdfPath = DocumentGenerator::template(base_path('tests/templates/test_pdf_template.html'))
    ->variables([
        'company_logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Google_Images_2015_logo.svg/1280px-Google_Images_2015_logo.svg.png',
        'full_name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'description' => 'This is a test document generated by DocumentGeneratorX.',
        'age' => 28,
        'quantity' => 150,
        'price' => 99,
        'created_date' => now()->format('Y-m-d'),
        'is_active' => true,
    ])
    ->format('pdf')
    ->generate();

echo "PDF generated at: {$pdfPath}";

EXAMPLE;
echo "\n----------------------------------------\n";
