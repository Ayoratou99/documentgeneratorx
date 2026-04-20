<?php

namespace Ayoratoumvone\Documentgeneratorx\Tests\Feature;

use Ayoratoumvone\Documentgeneratorx\DocumentGenerator;
use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use Ayoratoumvone\Documentgeneratorx\Processors\ImageProcessor;
use Ayoratoumvone\Documentgeneratorx\Generators\DocxToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Generators\HtmlToPdfGenerator;
use Ayoratoumvone\Documentgeneratorx\Exceptions\DocumentGeneratorException;
use Orchestra\Testbench\TestCase;

class DocumentGeneratorTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Ayoratoumvone\Documentgeneratorx\DocumentGeneratorServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'DocumentGenerator' => \Ayoratoumvone\Documentgeneratorx\Facades\DocumentGenerator::class,
        ];
    }

    // ─── Parser basics ───────────────────────────────────────────────

    /** @test */
    public function it_can_parse_text_variables()
    {
        $parser = new VariableParser();
        $result = $parser->parse('Hello {{name:text}}!');

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('text', $result['name']['type']);
    }

    /** @test */
    public function it_can_parse_number_variables()
    {
        $parser = new VariableParser();
        $result = $parser->parse('Age: {{age:number}}');

        $this->assertArrayHasKey('age', $result);
        $this->assertEquals('number', $result['age']['type']);
    }

    /** @test */
    public function it_can_parse_image_variables_with_dimensions()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{logo:image,width:200,height:100}}');

        $this->assertArrayHasKey('logo', $result);
        $this->assertEquals('image', $result['logo']['type']);
        $this->assertEquals('200', $result['logo']['options']['width']);
        $this->assertEquals('100', $result['logo']['options']['height']);
    }

    /** @test */
    public function it_can_parse_image_variables_with_ratio()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{banner:image,width:400,ratio:16:9}}');

        $this->assertArrayHasKey('banner', $result);
        $this->assertEquals('image', $result['banner']['type']);
        $this->assertEquals('400', $result['banner']['options']['width']);
        $this->assertEquals('16:9', $result['banner']['options']['ratio']);

        $dimensions = $parser->getImageDimensions($result['banner']['options']);
        $this->assertEquals(400, $dimensions['width']);
        $this->assertEquals(16, $dimensions['ratio']['width']);
        $this->assertEquals(9, $dimensions['ratio']['height']);
    }

    // ─── Validation ──────────────────────────────────────────────────

    /** @test */
    public function it_validates_text_values()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{name:text}}');

        $this->assertTrue($parser->validateValue($result['name'], 'John Doe'));
        $this->assertTrue($parser->validateValue($result['name'], 12345));
    }

    /** @test */
    public function it_validates_number_values()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{age:number}}');

        $this->assertTrue($parser->validateValue($result['age'], 25));
        $this->assertTrue($parser->validateValue($result['age'], '25'));
        $this->assertTrue($parser->validateValue($result['age'], 25.5));
    }

    /** @test */
    public function it_validates_boolean_values()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{active:boolean}}');

        $this->assertTrue($parser->validateValue($result['active'], true));
        $this->assertTrue($parser->validateValue($result['active'], false));
    }

    /** @test */
    public function it_validates_date_values()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{created:date}}');

        $this->assertTrue($parser->validateValue($result['created'], '2024-01-15'));
        $this->assertTrue($parser->validateValue($result['created'], new \DateTime()));
    }

    // ─── DocumentGenerator API ───────────────────────────────────────

    /** @test */
    public function it_can_set_and_get_variables()
    {
        $generator = new DocumentGenerator();
        $generator->variables([
            'name' => 'John',
            'age' => 25,
        ]);

        $variables = $generator->getVariables();

        $this->assertEquals('John', $variables['name']);
        $this->assertEquals(25, $variables['age']);
    }

    /** @test */
    public function it_can_add_single_variable()
    {
        $generator = new DocumentGenerator();
        $generator->addVariable('name', 'John');
        $generator->addVariable('age', 25);

        $variables = $generator->getVariables();

        $this->assertCount(2, $variables);
        $this->assertEquals('John', $variables['name']);
    }

    /** @test */
    public function exception_has_static_factory_methods()
    {
        $ex1 = DocumentGeneratorException::invalidTemplate('/path/to/file');
        $this->assertStringContainsString('Invalid', $ex1->getMessage());

        $ex2 = DocumentGeneratorException::templateNotFound('/missing.docx');
        $this->assertStringContainsString('not found', $ex2->getMessage());

        $ex3 = DocumentGeneratorException::unsupportedFormat('xyz');
        $this->assertStringContainsString('Unsupported', $ex3->getMessage());
    }

    // ─── Spaced placeholder parsing ──────────────────────────────────

    /** @test */
    public function it_parses_placeholders_with_spaces_around_colon()
    {
        $parser = new VariableParser();

        $result = $parser->parse('{{ nom : text }}');
        $this->assertArrayHasKey('nom', $result);
        $this->assertEquals('text', $result['nom']['type']);
    }

    /** @test */
    public function it_parses_placeholder_with_space_before_colon()
    {
        $parser = new VariableParser();

        $result = $parser->parse('{{nom :text}}');
        $this->assertArrayHasKey('nom', $result);
        $this->assertEquals('text', $result['nom']['type']);
    }

    /** @test */
    public function it_parses_placeholder_with_space_after_colon()
    {
        $parser = new VariableParser();

        $result = $parser->parse('{{nom: text}}');
        $this->assertArrayHasKey('nom', $result);
        $this->assertEquals('text', $result['nom']['type']);
    }

    /** @test */
    public function placeholder_pattern_matches_spaced_variants()
    {
        $parser = new VariableParser();
        $info = [
            'name' => 'nom',
            'type' => 'text',
            'options' => [],
            'styles' => [],
            'original' => '{{nom:text}}',
        ];

        $pattern = $parser->getPlaceholderPattern($info);

        $this->assertMatchesRegularExpression($pattern, '{{nom:text}}');
        $this->assertMatchesRegularExpression($pattern, '{{ nom : text }}');
        $this->assertMatchesRegularExpression($pattern, '{{nom :text}}');
        $this->assertMatchesRegularExpression($pattern, '{{nom: text}}');
        $this->assertMatchesRegularExpression($pattern, '{{ nom:text }}');
    }

    // ─── Missing-variable cleanup ────────────────────────────────────

    /** @test */
    public function missing_variables_are_replaced_with_blank_in_html()
    {
        $generator = new HtmlToPdfGenerator();

        $html = '<html><body><p>Hello {{name:text}}, your code is {{code:text}}</p></body></html>';
        $templatePath = tempnam(sys_get_temp_dir(), 'tpl_') . '.html';
        file_put_contents($templatePath, $html);

        $outputPath = tempnam(sys_get_temp_dir(), 'out_') . '.pdf';

        $generator->generate($templatePath, ['name' => 'Alice'], $outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));

        @unlink($templatePath);
        @unlink($outputPath);
    }

    /** @test */
    public function missing_variables_produce_blank_in_docx_xml()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'processDocxTemplate');
        $method->setAccessible(true);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Hello {{name:text}}, missing: {{missing_var:text}}');

        $templatePath = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($templatePath);

        $processedPath = $method->invoke($generator, $templatePath, ['name' => 'Alice']);

        $zip = new \ZipArchive();
        $zip->open($processedPath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Alice', $xml);
        $this->assertStringNotContainsString('{{missing_var', $xml);

        @unlink($templatePath);
        @unlink($processedPath);
    }

    // ─── Image XML structure (inline, keeps paragraph, no z-index issue) ──

    /** @test */
    public function image_xml_uses_inline_inside_run()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'createImageXml');
        $method->setAccessible(true);

        $xml = $method->invoke($generator, 'rId10', 1905000, 952500, 'test_image');

        $this->assertStringContainsString('<wp:inline', $xml);
        $this->assertStringNotContainsString('<wp:anchor', $xml);
    }

    /** @test */
    public function image_xml_does_not_break_paragraph()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'createImageXml');
        $method->setAccessible(true);

        $xml = $method->invoke($generator, 'rId10', 1905000, 952500, 'test_image');

        $this->assertStringNotContainsString('</w:p>', $xml);
        $this->assertStringNotContainsString('<w:p>', $xml);
    }

    /** @test */
    public function image_xml_carries_the_expected_dimensions()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'createImageXml');
        $method->setAccessible(true);

        $xml = $method->invoke($generator, 'rId10', 1905000, 952500, 'test_image');

        $this->assertStringContainsString('cx="1905000"', $xml);
        $this->assertStringContainsString('cy="952500"', $xml);
    }

    /** @test */
    public function image_xml_references_the_given_relationship_id()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'createImageXml');
        $method->setAccessible(true);

        $xml = $method->invoke($generator, 'rId42', 1905000, 952500, 'photo_profil');

        $this->assertStringContainsString('r:embed="rId42"', $xml);
        $this->assertStringContainsString('name="photo_profil"', $xml);
    }

    // ─── Fragmented placeholders in real DOCX XML ──────────────────────

    /** @test */
    public function it_merges_placeholders_whose_opening_braces_are_in_different_runs()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'fixFragmentedPlaceholders');
        $method->setAccessible(true);

        // Mirrors the exact fragmentation we observed in the fiche template:
        // ':{'  is in one <w:t>, '{' in another, the name and the type
        // in two more runs, then '}}' at the end.
        $fragmented =
            '<w:r><w:t>Pays :{</w:t></w:r>' .
            '<w:proofErr w:type="gramEnd"/>' .
            '<w:r><w:t>{</w:t></w:r>' .
            '<w:proofErr w:type="spellStart"/>' .
            '<w:r><w:t>pays</w:t></w:r>' .
            '<w:r><w:t>:text,bold:true</w:t></w:r>' .
            '<w:proofErr w:type="spellEnd"/>' .
            '<w:r><w:t xml:space="preserve">}} </w:t></w:r>';

        $fixed = $method->invoke($generator, $fragmented);

        // After merging, a single contiguous placeholder must exist.
        $this->assertMatchesRegularExpression(
            '/<w:t[^>]*>[^<]*\{\{pays:text,bold:true\}\}[^<]*<\/w:t>/',
            $fixed
        );
    }

    /** @test */
    public function it_fully_replaces_fragmented_placeholders_end_to_end()
    {
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'processDocxTemplate');
        $method->setAccessible(true);

        // Build a DOCX whose placeholder is split by a style change mid-word.
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $para = $section->addTextRun();
        $para->addText('Hello {');
        $para->addText('{name', ['bold' => true]);
        $para->addText(':text}}!');

        $templatePath = tempnam(sys_get_temp_dir(), 'tpl_') . '.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($templatePath);

        $processedPath = $method->invoke($generator, $templatePath, ['name' => 'Alice']);

        $zip = new \ZipArchive();
        $zip->open($processedPath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Alice', $xml);
        $this->assertStringNotContainsString('{{name', $xml);
        $this->assertStringNotContainsString('{{', $xml);

        @unlink($templatePath);
        @unlink($processedPath);
    }

    /** @test */
    public function it_handles_two_placeholders_that_share_a_single_w_t_run()
    {
        // Real-world pattern observed in FICHE_IDENTIFICATION_VEHICULE_TEMPLATE:
        // one <w:t> holds the closing "}}" of placeholder A and the opening
        // "{{" of placeholder B (e.g. "<w:t>}} {{</w:t>"). Both placeholders
        // therefore touch that same segment. Previously this produced
        // corrupted XML (duplicated braces / swallowed placeholders); the new
        // per-segment rewrite has to emit exactly one complete placeholder
        // for A and one for B.
        $generator = new DocxToPdfGenerator();
        $method = new \ReflectionMethod($generator, 'fixFragmentedPlaceholders');
        $method->setAccessible(true);

        $fragmented =
            '<w:r><w:t xml:space="preserve">Naiss : {</w:t></w:r>' .
            '<w:r><w:t>{date_naiss</w:t></w:r>' .
            '<w:r><w:t>:text</w:t></w:r>' .
            '<w:r><w:t>}} à {</w:t></w:r>' .   // share: closes A, opens B
            '<w:r><w:t>{lieu</w:t></w:r>' .
            '<w:r><w:t>:text}}</w:t></w:r>';

        $fixed = $method->invoke($generator, $fragmented);

        $this->assertMatchesRegularExpression(
            '/<w:t[^>]*>[^<]*\{\{date_naiss:text\}\}[^<]*<\/w:t>/',
            $fixed
        );
        $this->assertMatchesRegularExpression(
            '/<w:t[^>]*>[^<]*\{\{lieu:text\}\}[^<]*<\/w:t>/',
            $fixed
        );

        $this->assertSame(2, substr_count($fixed, '{{'));
        $this->assertSame(2, substr_count($fixed, '}}'));
    }
}
