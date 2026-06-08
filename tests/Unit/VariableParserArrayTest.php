<?php

declare(strict_types=1);

namespace Ayoratoumvone\Documentgeneratorx\Tests\Unit;

use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use PHPUnit\Framework\TestCase;

class VariableParserArrayTest extends TestCase
{
    private VariableParser $parser;

    protected function setUp(): void
    {
        $this->parser = new VariableParser();
    }

    public function testParsesArrayType(): void
    {
        $vars = $this->parser->parse('<w:t>{{nums:array}}</w:t>');

        $this->assertArrayHasKey('nums', $vars);
        $this->assertSame('array', $vars['nums']['type']);
        $this->assertSame('nums', $vars['nums']['name']);
    }

    public function testParsesArrayTypeWithStyles(): void
    {
        $vars = $this->parser->parse('<w:t>{{noms:array,bold:true,color:red}}</w:t>');

        $this->assertSame('array', $vars['noms']['type']);
        $this->assertSame('bold', $vars['noms']['styles']['bold']);
        $this->assertSame('red', $vars['noms']['styles']['color']);
    }

    public function testPlaceholderPatternMatchesPlainAndSpacedArray(): void
    {
        $info = $this->parser->parse('<w:t>{{nums:array}}</w:t>')['nums'];
        $pattern = $this->parser->getPlaceholderPattern($info);

        $this->assertSame(1, preg_match($pattern, '{{nums:array}}'));
        $this->assertSame(1, preg_match($pattern, '{{ nums : array }}'));
        $this->assertSame(1, preg_match($pattern, '{{nums:array,bold:true}}'));
        // Must not match a different variable name.
        $this->assertSame(0, preg_match($pattern, '{{other:array}}'));
    }

    public function testFormatValueNormalisesArray(): void
    {
        $info = ['name' => 'x', 'type' => 'array', 'options' => [], 'styles' => []];

        $this->assertSame(['a', 'b'], $this->parser->formatValue($info, ['a', 'b']));
        // Non-sequential keys are re-indexed.
        $this->assertSame(['a', 'b'], $this->parser->formatValue($info, [5 => 'a', 9 => 'b']));
    }

    public function testValidateValueAcceptsArrayRejectsScalar(): void
    {
        $info = ['name' => 'x', 'type' => 'array', 'options' => [], 'styles' => []];

        $this->assertTrue($this->parser->validateValue($info, ['a', 'b']));
        $this->assertFalse($this->parser->validateValue($info, 'not-an-array'));
    }
}
