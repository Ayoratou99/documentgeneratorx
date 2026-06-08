<?php

declare(strict_types=1);

namespace Ayoratoumvone\Documentgeneratorx\Tests\Feature;

use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

/**
 * Exercises the WHOLE docx pipeline (fragment repair -> array expansion ->
 * scalar replacement -> cleanup) on a genuine .docx package, stopping just
 * before PDF conversion so the test needs no LibreOffice.
 *
 * The template mirrors the brief's screenshot: a 4-column table
 * (Numero | Nom | Q | Dimission) whose first data row holds array
 * placeholders, followed by spare blank rows.
 */
class DocxArrayExpansionTest extends TestCase
{
    private array $tempFiles = [];

    protected function setUp(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('The zip extension is required to build a .docx.');
        }
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to construct DocxToPdfGenerator (ImageProcessor).');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function testArrayTableExpandsInRealDocx(): void
    {
        $documentXml = $this->buildDocumentXml();
        $templatePath = $this->makeDocx($documentXml);

        $generator = new DocxToPdfGenerator();

        $method = new ReflectionMethod($generator, 'processDocxTemplate');
        $method->setAccessible(true);

        $processedPath = $method->invoke($generator, $templatePath, [
            'title' => 'Inventaire',
            'nums'  => ['1', '2', '3', '4', '5'],
            'noms'  => ['Marteau', 'Scie', 'Clou', 'Vis', 'Pince'],
            'qs'    => ['10', '5', '200', '500', '8'],
            'dims'  => ['20cm', '40cm', '3cm', '2cm', '15cm'],
        ]);
        $this->tempFiles[] = $processedPath;

        $result = $this->readDocumentXml($processedPath);

        // Header row + 5 data rows; the spare blank rows were consumed.
        $this->assertSame(6, preg_match_all('/<w:tr\b/', $result), 'Expected header + 5 data rows.');

        // Every array value made it into the document, in every column.
        foreach (['1', '2', '3', '4', '5'] as $n) {
            $this->assertStringContainsString("<w:t>{$n}</w:t>", $result);
        }
        foreach (['Marteau', 'Scie', 'Clou', 'Vis', 'Pince'] as $nom) {
            $this->assertStringContainsString("<w:t>{$nom}</w:t>", $result);
        }
        foreach (['20cm', '40cm', '3cm', '2cm', '15cm'] as $dim) {
            $this->assertStringContainsString("<w:t>{$dim}</w:t>", $result);
        }

        // The scalar variable outside the table still resolved.
        $this->assertStringContainsString('Inventaire', $result);

        // No placeholders survive anywhere.
        $this->assertStringNotContainsString('{{', $result);
        $this->assertStringNotContainsString('}}', $result);
    }

    public function testFragmentedArrayPlaceholderIsRepairedThenExpanded(): void
    {
        // Word often splits a placeholder across runs. Here "{{noms:array}}"
        // is shredded into three runs — the pipeline must stitch it back
        // together before expanding.
        $brokenRow = '<w:tr>'
            . '<w:tc><w:p>'
            . '<w:r><w:t>{{no</w:t></w:r>'
            . '<w:r><w:t>ms:ar</w:t></w:r>'
            . '<w:r><w:t>ray}}</w:t></w:r>'
            . '</w:p></w:tc>'
            . '</w:tr>';

        $documentXml = $this->wrapBody(
            '<w:tbl><w:tblPr/><w:tblGrid/>'
            . '<w:tr><w:tc><w:p><w:r><w:t>Nom</w:t></w:r></w:p></w:tc></w:tr>'
            . $brokenRow
            . '</w:tbl>'
        );

        $templatePath = $this->makeDocx($documentXml);
        $generator = new DocxToPdfGenerator();
        $method = new ReflectionMethod($generator, 'processDocxTemplate');
        $method->setAccessible(true);

        $processedPath = $method->invoke($generator, $templatePath, [
            'noms' => ['Alpha', 'Beta'],
        ]);
        $this->tempFiles[] = $processedPath;

        $result = $this->readDocumentXml($processedPath);

        $this->assertSame(3, preg_match_all('/<w:tr\b/', $result), 'Expected header + 2 data rows.');
        $this->assertStringContainsString('Alpha', $result);
        $this->assertStringContainsString('Beta', $result);
        $this->assertStringNotContainsString('{{', $result);
    }

    // ---- helpers ---------------------------------------------------------

    private function buildDocumentXml(): string
    {
        $headerCells = '';
        foreach (['Numero', 'Nom', 'Q', 'Dimission'] as $label) {
            $headerCells .= "<w:tc><w:p><w:r><w:t>{$label}</w:t></w:r></w:p></w:tc>";
        }

        $arrayCells = '';
        foreach (['nums', 'noms', 'qs', 'dims'] as $name) {
            $arrayCells .= "<w:tc><w:p><w:r><w:t>{{{$name}:array}}</w:t></w:r></w:p></w:tc>";
        }

        $blankRow = '<w:tr>'
            . str_repeat('<w:tc><w:p><w:r><w:t></w:t></w:r></w:p></w:tc>', 4)
            . '</w:tr>';

        $table = '<w:tbl><w:tblPr/><w:tblGrid/>'
            . "<w:tr>{$headerCells}</w:tr>"
            . "<w:tr>{$arrayCells}</w:tr>"
            . $blankRow . $blankRow . $blankRow // three spare rows, as in the screenshot
            . '</w:tbl>';

        $heading = '<w:p><w:r><w:t>Description des objets : {{title:text}}</w:t></w:r></w:p>';

        return $this->wrapBody($heading . $table);
    }

    private function wrapBody(string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $inner . '</w:body></w:document>';
    }

    /** Build a minimal but valid .docx (OPC zip) carrying the given document.xml. */
    private function makeDocx(string $documentXml): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docgen_test_' . uniqid('', true) . '.docx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->assertTrue($opened === true, 'Could not create the test .docx archive.');

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>'
        );

        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
        );

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $path;
    }

    private function readDocumentXml(string $docxPath): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($docxPath) === true, "Could not open processed docx: {$docxPath}");
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotFalse($xml, 'Processed docx has no word/document.xml');

        return $xml;
    }
}
