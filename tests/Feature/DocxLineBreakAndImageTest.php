<?php

declare(strict_types=1);

namespace Ayoratoumvone\Documentgeneratorx\Tests\Feature;

use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Processors\ImageProcessor;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

/**
 * Line breaks in text values and image proportions, on a genuine .docx
 * package (stopping before PDF conversion, so no LibreOffice is needed).
 */
class DocxLineBreakAndImageTest extends TestCase
{
    private const EMU_PER_PIXEL = 9525;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        if (!extension_loaded('zip') || !extension_loaded('gd')) {
            $this->markTestSkipped('zip and gd are required.');
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

    // --- Line breaks ----------------------------------------------------------

    public function testLineBreaksInATextValueBecomeWordBreaks(): void
    {
        $xml = $this->generate($this->paragraph('{{note:text}}'), ['note' => "teste\nteste"]);

        $this->assertStringContainsString('teste</w:t><w:br/><w:t xml:space="preserve">teste', $xml);
        $this->assertWellFormed($xml);
    }

    public function testEmptyLinesAndWindowsLineEndingsAreKept(): void
    {
        $xml = $this->generate($this->paragraph('{{note:text}}'), ['note' => "un\r\n\r\ndeux\rtrois"]);

        // "\r\n" and "\r" are normalised; the empty line gives two breaks.
        $this->assertSame(3, substr_count($xml, '<w:br/>'));
        $this->assertStringContainsString('un</w:t><w:br/><w:t xml:space="preserve"></w:t><w:br/><w:t xml:space="preserve">deux', $xml);
        $this->assertStringNotContainsString("\r", $xml);
        $this->assertWellFormed($xml);
    }

    public function testTabsBecomeWordTabs(): void
    {
        $xml = $this->generate($this->paragraph('{{note:text}}'), ['note' => "Nom\tPrénom"]);

        $this->assertStringContainsString('Nom</w:t><w:tab/><w:t xml:space="preserve">Prénom', $xml);
        $this->assertWellFormed($xml);
    }

    public function testStyledValuesKeepTheirStyleAcrossLines(): void
    {
        $xml = $this->generate($this->paragraph('{{note:text,bold:true}}'), ['note' => "ligne 1\nligne 2"]);

        // Both lines stay in the single bold run: the break is inside it.
        $this->assertMatchesRegularExpression(
            '#<w:r><w:rPr>.*?<w:b/>.*?</w:rPr><w:t xml:space="preserve">ligne 1</w:t><w:br/><w:t xml:space="preserve">ligne 2</w:t></w:r>#s',
            $xml
        );
        $this->assertWellFormed($xml);
    }

    public function testUntypedPlaceholdersHonourLineBreaksToo(): void
    {
        $xml = $this->generate($this->paragraph('{{note}}'), ['note' => "a\nb"]);

        $this->assertStringContainsString('a</w:t><w:br/><w:t xml:space="preserve">b', $xml);
        $this->assertWellFormed($xml);
    }

    public function testUntypedPlaceholdersAreReplaced(): void
    {
        // Regression: {{name}} (no type) used to be erased by the clean-up.
        $xml = $this->generate(
            $this->paragraph('{{num_dossier}} / {{ lieu }} / {{titre:text,bold:true}}'),
            ['num_dossier' => 'BR-LBV-2026-00001', 'lieu' => 'Libreville', 'titre' => 'PV']
        );

        $this->assertStringContainsString('BR-LBV-2026-00001', $xml);
        $this->assertStringContainsString('Libreville', $xml);
        $this->assertStringContainsString('>PV</w:t>', $xml);
        $this->assertStringNotContainsString('{{', $xml);
        $this->assertWellFormed($xml);
    }

    public function testXmlSpecialCharactersAreStillEscaped(): void
    {
        $xml = $this->generate($this->paragraph('{{note:text}}'), ['note' => "<b>&\n\"x\""]);

        $this->assertStringContainsString('&lt;b&gt;&amp;</w:t><w:br/>', $xml);
        $this->assertWellFormed($xml);
    }

    public function testLineBreaksInsideArrayValuesAreKept(): void
    {
        $xml = $this->generate($this->paragraph('{{lignes:array}}'), ['lignes' => ["a\nb", 'c']]);

        // Items are separated by a break, and so are the lines of an item.
        $this->assertStringContainsString('a</w:t><w:br/><w:t xml:space="preserve">b</w:t><w:br/><w:t xml:space="preserve">c', $xml);
        $this->assertWellFormed($xml);
    }

    public function testSingleLineValuesAreUnchanged(): void
    {
        $xml = $this->generate($this->paragraph('{{note:text}}'), ['note' => 'une seule ligne']);

        $this->assertStringContainsString('<w:t>une seule ligne</w:t>', $xml);
        $this->assertStringNotContainsString('<w:br/>', $xml);
    }

    // --- Images ---------------------------------------------------------------

    public function testAWidthOnlyImageKeepsItsProportions(): void
    {
        // Portrait 300×400 photo, template asks for width:200 only.
        $xml = $this->generate(
            $this->paragraph('{{photo:image,width:200}}'),
            ['photo' => $this->png(300, 400)]
        );

        $this->assertExtent($xml, 200, 267);
        $this->assertWellFormed($xml);
    }

    public function testAHeightOnlyImageKeepsItsProportions(): void
    {
        $xml = $this->generate(
            $this->paragraph('{{photo:image,height:100}}'),
            ['photo' => $this->png(400, 200)]
        );

        $this->assertExtent($xml, 200, 100);
    }

    public function testAnImageFitsInsideAWidthAndHeightBox(): void
    {
        // Portrait photo in a square box: limited by the height.
        $xml = $this->generate(
            $this->paragraph('{{photo:image,width:200,height:200}}'),
            ['photo' => $this->png(300, 400)]
        );

        $this->assertExtent($xml, 150, 200);
    }

    public function testASmallImageIsDisplayedAtTheRequestedWidthWithoutDistortion(): void
    {
        // scaleDown() never enlarges pixels, but the display size must still
        // follow the template: 100×50 shown 200 wide → 200×100.
        $xml = $this->generate(
            $this->paragraph('{{photo:image,width:200}}'),
            ['photo' => $this->png(100, 50)]
        );

        $this->assertExtent($xml, 200, 100);
    }

    public function testAnImageWithoutDimensionsKeepsItsOwnSize(): void
    {
        $xml = $this->generate(
            $this->paragraph('{{photo:image}}'),
            ['photo' => $this->png(120, 90)]
        );

        $this->assertExtent($xml, 120, 90);
    }

    /** @dataProvider displaySizes */
    public function testDisplaySize(array $dimensions, int $w, int $h, array $expected): void
    {
        $this->assertSame($expected, (new ImageProcessor())->displaySize($dimensions, $w, $h));
    }

    public static function displaySizes(): array
    {
        return [
            'width only, portrait' => [['width' => 200, 'height' => null], 300, 400, ['width' => 200, 'height' => 267]],
            'width only, landscape' => [['width' => 200, 'height' => null], 400, 300, ['width' => 200, 'height' => 150]],
            'height only' => [['width' => null, 'height' => 120], 300, 400, ['width' => 90, 'height' => 120]],
            'box limited by width' => [['width' => 100, 'height' => 300], 200, 100, ['width' => 100, 'height' => 50]],
            'box limited by height' => [['width' => 300, 'height' => 100], 200, 400, ['width' => 50, 'height' => 100]],
            'no dimension' => [['width' => null, 'height' => null], 64, 48, ['width' => 64, 'height' => 48]],
            'unreadable image' => [['width' => 150, 'height' => null], 0, 0, ['width' => 150, 'height' => 100]],
        ];
    }

    // --- Helpers --------------------------------------------------------------

    private function assertExtent(string $xml, int $width, int $height): void
    {
        $this->assertMatchesRegularExpression('#<wp:extent cx="(\d+)" cy="(\d+)"/>#', $xml);
        preg_match('#<wp:extent cx="(\d+)" cy="(\d+)"/>#', $xml, $m);
        $this->assertSame(
            [$width, $height],
            [(int) $m[1] / self::EMU_PER_PIXEL, (int) $m[2] / self::EMU_PER_PIXEL],
            'Unexpected displayed size (pixels).'
        );
        // The drawing's own extent must match the inline extent.
        $this->assertStringContainsString('<a:ext cx="' . $m[1] . '" cy="' . $m[2] . '"/>', $xml);
    }

    private function assertWellFormed(string $xml): void
    {
        $dom = new DOMDocument();
        $this->assertTrue(@$dom->loadXML($xml), 'document.xml is no longer well-formed XML.');
    }

    private function png(int $width, int $height): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docgen_img_' . uniqid('', true) . '.png';
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        imagepng($image, $path);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function paragraph(string $placeholder): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<w:body><w:p><w:r><w:t>' . $placeholder . '</w:t></w:r></w:p></w:body></w:document>';
    }

    private function generate(string $documentXml, array $variables): string
    {
        $generator = new DocxToPdfGenerator();
        $method = new ReflectionMethod($generator, 'processDocxTemplate');

        $processed = $method->invoke($generator, $this->makeDocx($documentXml), $variables);
        $this->tempFiles[] = $processed;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($processed) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    private function makeDocx(string $documentXml): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docgen_test_' . uniqid('', true) . '.docx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
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
}
