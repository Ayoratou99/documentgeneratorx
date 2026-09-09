<?php

declare(strict_types=1);

namespace Ayoratoumvone\Documentgeneratorx\Tests\Feature;

use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

/**
 * Runs {{if}}…{{endif}} conditional blocks through the whole docx pipeline
 * (fragment repair -> conditional resolution -> variable replacement),
 * stopping before PDF conversion so no LibreOffice is required.
 */
class DocxConditionalTest extends TestCase
{
    private array $tempFiles = [];

    protected function setUp(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('The zip extension is required to build a .docx.');
        }
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to construct DocxToPdfGenerator.');
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

    public function testGoldBranchIsKeptAndFilled(): void
    {
        $result = $this->render(['membership' => 'gold', 'name' => 'Alice']);

        $this->assertStringContainsString('Gold member', $result);
        $this->assertStringContainsString('Alice', $result);          // inner variable filled
        $this->assertStringNotContainsString('Silver member', $result);
        $this->assertStringNotContainsString('Standard member', $result);
        $this->assertStringNotContainsString('{{', $result);          // no markers/placeholders left
    }

    public function testElseIfBranchIsKept(): void
    {
        $result = $this->render(['membership' => 'silver', 'name' => 'Bob']);

        $this->assertStringContainsString('Silver member', $result);
        $this->assertStringNotContainsString('Gold member', $result);
        $this->assertStringNotContainsString('Standard member', $result);
    }

    public function testElseBranchIsKeptWhenNothingMatches(): void
    {
        $result = $this->render(['membership' => 'bronze', 'name' => 'Cara']);

        $this->assertStringContainsString('Standard member', $result);
        $this->assertStringNotContainsString('Gold member', $result);
        $this->assertStringNotContainsString('Silver member', $result);
    }

    // ---- helpers ---------------------------------------------------------

    private function render(array $variables): string
    {
        $templatePath = $this->makeDocx($this->buildDocumentXml());

        $generator = new DocxToPdfGenerator();
        $method = new ReflectionMethod($generator, 'processDocxTemplate');
        $method->setAccessible(true);

        $processed = $method->invoke($generator, $templatePath, $variables);
        $this->tempFiles[] = $processed;

        return $this->readDocumentXml($processed);
    }

    private function buildDocumentXml(): string
    {
        $p = static fn (string $t): string => '<w:p><w:r><w:t>' . $t . '</w:t></w:r></w:p>';

        $body = $p('Membership card for {{name:text}}')
            . $p('{{if:membership=gold}}')
            . $p('Gold member - premium benefits')
            . $p('{{elseif:membership=silver}}')
            . $p('Silver member - extended benefits')
            . $p('{{else}}')
            . $p('Standard member')
            . $p('{{endif}}');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body . '</w:body></w:document>';
    }

    private function makeDocx(string $documentXml): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docgen_cond_' . uniqid('', true) . '.docx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

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
