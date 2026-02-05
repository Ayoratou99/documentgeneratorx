<?php

namespace Ayoratoumvone\Documentgeneratorx\Tests\Feature;

use Ayoratoumvone\Documentgeneratorx\DocumentGenerator;
use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use Ayoratoumvone\Documentgeneratorx\Processors\ImageProcessor;
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

    /** @test */
    public function it_validates_text_values()
    {
        $parser = new VariableParser();
        $result = $parser->parse('{{name:text}}');
        
        $this->assertTrue($parser->validateValue($result['name'], 'John Doe'));
        $this->assertTrue($parser->validateValue($result['name'], 12345)); // Numbers are valid as text
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

    /** @test */
    public function it_throws_exception_for_invalid_format()
    {
        $this->expectException(DocumentGeneratorException::class);
        
        $generator = new DocumentGenerator();
        $generator->format('invalid');
    }

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
    public function it_can_set_output_format()
    {
        $generator = new DocumentGenerator();
        
        $generator->format('pdf');
        $this->assertEquals('pdf', $generator->getOutputFormat());
        
        $generator->format('docx');
        $this->assertEquals('docx', $generator->getOutputFormat());
    }

    /** @test */
    public function it_can_reset_state()
    {
        $generator = new DocumentGenerator();
        $generator->variables(['name' => 'John']);
        $generator->format('pdf');
        
        $generator->reset();
        
        $this->assertEmpty($generator->getVariables());
        $this->assertEquals('docx', $generator->getOutputFormat());
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
}
