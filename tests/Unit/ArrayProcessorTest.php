<?php

declare(strict_types=1);

namespace Ayoratoumvone\Documentgeneratorx\Tests\Unit;

use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;
use Ayoratoumvone\Documentgeneratorx\Processors\ArrayProcessor;
use PHPUnit\Framework\TestCase;

class ArrayProcessorTest extends TestCase
{
    private VariableParser $parser;
    private ArrayProcessor $processor;

    protected function setUp(): void
    {
        $this->parser = new VariableParser();
        $this->processor = new ArrayProcessor($this->parser);
    }

    /** Run the processor, parsing the variables straight from the XML like the real pipeline. */
    private function expand(string $xml, array $values): string
    {
        $templateVariables = $this->parser->parse($xml);

        return $this->processor->process($xml, $templateVariables, $values);
    }

    private function cell(string $content): string
    {
        return "<w:tc><w:tcPr><w:tcW w:w=\"2000\" w:type=\"dxa\"/></w:tcPr>"
            . "<w:p><w:r><w:t>{$content}</w:t></w:r></w:p></w:tc>";
    }

    private function row(string ...$cells): string
    {
        return '<w:tr>' . implode('', $cells) . '</w:tr>';
    }

    private function table(string ...$rows): string
    {
        return '<w:tbl><w:tblPr/><w:tblGrid/>' . implode('', $rows) . '</w:tbl>';
    }

    private function countRows(string $xml): int
    {
        return preg_match_all('/<w:tr\b/', $xml);
    }

    public function testClonesRowPerArrayElementInSameColumn(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array}}'))
        );

        $out = $this->expand($xml, ['noms' => ['Alice', 'Bob', 'Carol']]);

        // Header + 3 data rows.
        $this->assertSame(4, $this->countRows($out));
        $this->assertStringContainsString('<w:t>Alice</w:t>', $out);
        $this->assertStringContainsString('<w:t>Bob</w:t>', $out);
        $this->assertStringContainsString('<w:t>Carol</w:t>', $out);
        $this->assertStringNotContainsString('{{noms:array}}', $out);
        // Values keep their order.
        $this->assertLessThan(strpos($out, 'Bob'), strpos($out, 'Alice'));
        $this->assertLessThan(strpos($out, 'Carol'), strpos($out, 'Bob'));
    }

    public function testParallelColumnsUseLongestArrayAndPadShorter(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Numero'), $this->cell('Nom')),
            $this->row($this->cell('{{nums:array}}'), $this->cell('{{noms:array}}'))
        );

        $out = $this->expand($xml, [
            'nums' => ['1', '2', '3'],
            'noms' => ['a', 'b'], // shorter on purpose
        ]);

        // Header + 3 rows (longest array wins).
        $this->assertSame(4, $this->countRows($out));
        $this->assertStringContainsString('<w:t>3</w:t>', $out);
        // The third row's "Nom" cell is blank, not a leftover placeholder.
        $this->assertStringNotContainsString('{{noms:array}}', $out);
        $this->assertStringNotContainsString('{{nums:array}}', $out);
    }

    public function testAutoAddsRowsBeyondDrawnEmptyRows(): void
    {
        // Author drew the template row + 2 spare blank rows (as in the brief's image).
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array}}')),
            $this->row($this->cell('')),
            $this->row($this->cell(''))
        );

        $out = $this->expand($xml, ['noms' => ['a', 'b', 'c', 'd', 'e']]);

        // Header + exactly 5 data rows — drawn blanks consumed, extras auto-added.
        $this->assertSame(6, $this->countRows($out));
        foreach (['a', 'b', 'c', 'd', 'e'] as $v) {
            $this->assertStringContainsString("<w:t>{$v}</w:t>", $out);
        }
    }

    public function testConsumesDrawnEmptyRowsWhenDataIsShorter(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array}}')),
            $this->row($this->cell('')),
            $this->row($this->cell('')),
            $this->row($this->cell(''))
        );

        $out = $this->expand($xml, ['noms' => ['a', 'b']]);

        // Header + 2 data rows. The 3 drawn blank rows are absorbed, none left over.
        $this->assertSame(3, $this->countRows($out));
    }

    public function testEmptyArrayRemovesTemplateAndDrawnRows(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array}}')),
            $this->row($this->cell(''))
        );

        $out = $this->expand($xml, ['noms' => []]);

        // Only the header survives; no stray placeholder.
        $this->assertSame(1, $this->countRows($out));
        $this->assertStringNotContainsString('{{noms:array}}', $out);
    }

    public function testScalarValueIsTreatedAsSingleElement(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array}}'))
        );

        $out = $this->expand($xml, ['noms' => 'Solo']);

        $this->assertSame(2, $this->countRows($out));
        $this->assertStringContainsString('<w:t>Solo</w:t>', $out);
    }

    public function testEscapesXmlSpecialCharacters(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array}}'))
        );

        $out = $this->expand($xml, ['noms' => ['Tom & Jerry', '<script>', 'a > b']]);

        $this->assertStringContainsString('Tom &amp; Jerry', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('a &gt; b', $out);
        // Raw, unescaped specials must not leak into the document body text.
        $this->assertStringNotContainsString('<w:t>Tom & Jerry</w:t>', $out);
    }

    public function testAppliesPerCellStyles(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{noms:array,bold:true}}'))
        );

        $out = $this->expand($xml, ['noms' => ['Alice', 'Bob']]);

        // Bold run property emitted, and the values still present.
        $this->assertStringContainsString('<w:b/>', $out);
        $this->assertStringContainsString('Alice', $out);
        $this->assertStringContainsString('Bob', $out);
        $this->assertSame(3, $this->countRows($out)); // header + 2
    }

    public function testLeavesScalarPlaceholdersUntouchedForLaterPass(): void
    {
        $xml = '<w:p><w:r><w:t>{{title:text}}</w:t></w:r></w:p>'
            . $this->table(
                $this->row($this->cell('Nom')),
                $this->row($this->cell('{{noms:array}}'))
            );

        $out = $this->expand($xml, ['noms' => ['a'], 'title' => 'ignored-here']);

        // The array expands, but the scalar placeholder is left for the scalar pass.
        $this->assertStringContainsString('{{title:text}}', $out);
        $this->assertStringContainsString('<w:t>a</w:t>', $out);
    }

    public function testTwoSeparateTablesExpandIndependently(): void
    {
        $xml = $this->table(
            $this->row($this->cell('A')),
            $this->row($this->cell('{{xs:array}}'))
        ) . $this->table(
            $this->row($this->cell('B')),
            $this->row($this->cell('{{ys:array}}'))
        );

        $out = $this->expand($xml, [
            'xs' => ['x1', 'x2'],
            'ys' => ['y1', 'y2', 'y3'],
        ]);

        // Table 1: header + 2; Table 2: header + 3 = 7 rows total.
        $this->assertSame(7, $this->countRows($out));
        $this->assertStringContainsString('<w:t>x2</w:t>', $out);
        $this->assertStringContainsString('<w:t>y3</w:t>', $out);
    }

    public function testInlineArrayOutsideTableJoinsWithLineBreaks(): void
    {
        $xml = '<w:p><w:r><w:t>{{tags:array}}</w:t></w:r></w:p>';

        $out = $this->expand($xml, ['tags' => ['red', 'green', 'blue']]);

        $this->assertStringContainsString('red', $out);
        $this->assertStringContainsString('green', $out);
        $this->assertStringContainsString('blue', $out);
        // Two <w:br/> separate the three values; placeholder consumed.
        $this->assertSame(2, substr_count($out, '<w:br/>'));
        $this->assertStringNotContainsString('{{tags:array}}', $out);
    }

    public function testReturnsXmlUnchangedWhenNoArrayVariables(): void
    {
        $xml = $this->table(
            $this->row($this->cell('Nom')),
            $this->row($this->cell('{{name:text}}'))
        );

        $out = $this->expand($xml, ['name' => 'whatever']);

        $this->assertSame($xml, $out);
    }
}
