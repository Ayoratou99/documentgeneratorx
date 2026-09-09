<?php

declare(strict_types=1);

namespace Ayoratoumvone\Documentgeneratorx\Tests\Unit;

use Ayoratoumvone\Documentgeneratorx\Processors\ConditionalProcessor;
use PHPUnit\Framework\TestCase;

class ConditionalProcessorTest extends TestCase
{
    private ConditionalProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ConditionalProcessor();
    }

    /** A block-level paragraph carrying a single marker or some text. */
    private function p(string $text): string
    {
        return '<w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p>';
    }

    // ─── Inline mode ──────────────────────────────────────────────────

    public function testInlineKeepsBlockWhenEqualityTrue(): void
    {
        $xml = '<w:p><w:r><w:t>Grade: {{if:grade=A}}Excellent{{endif}}.</w:t></w:r></w:p>';

        $out = $this->processor->process($xml, ['grade' => 'A']);

        $this->assertStringContainsString('Excellent', $out);
        $this->assertStringNotContainsString('{{', $out);
        $this->assertStringContainsString('Grade: Excellent.', $out);
    }

    public function testInlineDropsBlockWhenEqualityFalse(): void
    {
        $xml = '<w:p><w:r><w:t>Grade: {{if:grade=A}}Excellent{{endif}}.</w:t></w:r></w:p>';

        $out = $this->processor->process($xml, ['grade' => 'B']);

        $this->assertStringNotContainsString('Excellent', $out);
        $this->assertStringNotContainsString('{{', $out);
        $this->assertStringContainsString('Grade: .', $out);
    }

    public function testInlineElseBranch(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:vip}}Gold{{else}}Standard{{endif}}</w:t></w:r></w:p>';

        $this->assertStringContainsString('Gold', $this->processor->process($xml, ['vip' => true]));
        $this->assertStringContainsString('Standard', $this->processor->process($xml, ['vip' => false]));
    }

    public function testInlineElseIfChainPicksSecondBranch(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:g=A}}A!{{elseif:g=B}}B!{{else}}other{{endif}}</w:t></w:r></w:p>';

        $out = $this->processor->process($xml, ['g' => 'B']);

        $this->assertStringContainsString('B!', $out);
        $this->assertStringNotContainsString('A!', $out);
        $this->assertStringNotContainsString('other', $out);
    }

    public function testNotEqualOperator(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:status!=active}}INACTIVE{{endif}}</w:t></w:r></w:p>';

        $this->assertStringContainsString('INACTIVE', $this->processor->process($xml, ['status' => 'banned']));
        $this->assertStringNotContainsString('INACTIVE', $this->processor->process($xml, ['status' => 'active']));
    }

    public function testTruthyMissingVariableIsFalse(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:flag}}YES{{endif}}</w:t></w:r></w:p>';

        $this->assertStringNotContainsString('YES', $this->processor->process($xml, []));
        $this->assertStringNotContainsString('YES', $this->processor->process($xml, ['flag' => '']));
        $this->assertStringNotContainsString('YES', $this->processor->process($xml, ['flag' => '0']));
        $this->assertStringContainsString('YES', $this->processor->process($xml, ['flag' => 'x']));
    }

    public function testQuotedValueWithSpaces(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:name="John Doe"}}hi{{endif}}</w:t></w:r></w:p>';

        $this->assertStringContainsString('hi', $this->processor->process($xml, ['name' => 'John Doe']));
        $this->assertStringNotContainsString('hi', $this->processor->process($xml, ['name' => 'Jane']));
    }

    public function testEqualityIsCaseSensitive(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:g=A}}hit{{endif}}</w:t></w:r></w:p>';

        $this->assertStringNotContainsString('hit', $this->processor->process($xml, ['g' => 'a']));
    }

    // ─── Block mode ───────────────────────────────────────────────────

    public function testBlockModeKeepsBranchParagraphsAndDropsMarkers(): void
    {
        $xml = $this->p('{{if:vip}}')
            . $this->p('Welcome VIP')
            . $this->p('Enjoy your perks')
            . $this->p('{{endif}}');

        $out = $this->processor->process($xml, ['vip' => true]);

        // Two content paragraphs kept, both marker paragraphs gone.
        $this->assertSame(2, substr_count($out, '<w:p>'));
        $this->assertStringContainsString('Welcome VIP', $out);
        $this->assertStringContainsString('Enjoy your perks', $out);
        $this->assertStringNotContainsString('{{', $out);
    }

    public function testBlockModeDropsEntireBlockWhenFalse(): void
    {
        $xml = $this->p('Intro')
            . $this->p('{{if:vip}}')
            . $this->p('secret')
            . $this->p('{{endif}}')
            . $this->p('Outro');

        $out = $this->processor->process($xml, ['vip' => false]);

        // Only Intro + Outro remain; no blank paragraphs from the markers.
        $this->assertSame(2, substr_count($out, '<w:p>'));
        $this->assertStringContainsString('Intro', $out);
        $this->assertStringContainsString('Outro', $out);
        $this->assertStringNotContainsString('secret', $out);
    }

    public function testBlockModeElseBranch(): void
    {
        $xml = $this->p('{{if:g=A}}')
            . $this->p('grade A')
            . $this->p('{{else}}')
            . $this->p('other grade')
            . $this->p('{{endif}}');

        $out = $this->processor->process($xml, ['g' => 'C']);

        $this->assertStringContainsString('other grade', $out);
        $this->assertStringNotContainsString('grade A', $out);
        $this->assertSame(1, substr_count($out, '<w:p>'));
    }

    public function testBlockModeKeepsTableInsideBranch(): void
    {
        $table = '<w:tbl><w:tr><w:tc>' . $this->p('cell') . '</w:tc></w:tr></w:tbl>';
        $xml = $this->p('{{if:show}}') . $table . $this->p('{{endif}}');

        $kept = $this->processor->process($xml, ['show' => true]);
        $this->assertStringContainsString('<w:tbl>', $kept);
        $this->assertStringContainsString('cell', $kept);

        $dropped = $this->processor->process($xml, ['show' => false]);
        $this->assertStringNotContainsString('<w:tbl>', $dropped);
    }

    // ─── Nesting & multiples ─────────────────────────────────────────

    public function testNestedConditionals(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:a}}A{{if:b}}B{{endif}}{{endif}}</w:t></w:r></w:p>';

        $this->assertStringContainsString('AB', $this->processor->process($xml, ['a' => true, 'b' => true]));

        $onlyA = $this->processor->process($xml, ['a' => true, 'b' => false]);
        $this->assertStringContainsString('A', $onlyA);
        $this->assertStringNotContainsString('B', $onlyA);

        $none = $this->processor->process($xml, ['a' => false, 'b' => true]);
        $this->assertStringNotContainsString('A', $none);
        $this->assertStringNotContainsString('B', $none);
    }

    public function testMultipleIndependentConditionals(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:x}}X{{endif}}-{{if:y}}Y{{endif}}</w:t></w:r></w:p>';

        $out = $this->processor->process($xml, ['x' => true, 'y' => false]);

        $this->assertStringContainsString('X', $out);
        $this->assertStringNotContainsString('Y', $out);
        $this->assertStringNotContainsString('{{', $out);
    }

    public function testLeavesInnerVariablePlaceholdersForLaterPass(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:show}}Hello {{name:text}}{{endif}}</w:t></w:r></w:p>';

        $out = $this->processor->process($xml, ['show' => true]);

        // The conditional resolves; the scalar placeholder survives for the
        // normal replacement pass that runs afterwards.
        $this->assertStringContainsString('{{name:text}}', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function testDroppedBranchRemovesInnerVariablePlaceholder(): void
    {
        $xml = '<w:p><w:r><w:t>{{if:show}}secret {{token:text}}{{endif}}</w:t></w:r></w:p>';

        $out = $this->processor->process($xml, ['show' => false]);

        $this->assertStringNotContainsString('{{token:text}}', $out);
        $this->assertStringNotContainsString('secret', $out);
    }

    public function testNoMarkersReturnsXmlUnchanged(): void
    {
        $xml = '<w:p><w:r><w:t>{{name:text}} and {{age:number}}</w:t></w:r></w:p>';

        $this->assertSame($xml, $this->processor->process($xml, ['name' => 'Jo']));
    }

    public function testDateTimeAndNumberEquality(): void
    {
        $date = new \DateTimeImmutable('2026-01-15');
        $xmlDate = '<w:p><w:r><w:t>{{if:d=2026-01-15}}ok{{endif}}</w:t></w:r></w:p>';
        $this->assertStringContainsString('ok', $this->processor->process($xmlDate, ['d' => $date]));

        $xmlNum = '<w:p><w:r><w:t>{{if:age=25}}adult{{endif}}</w:t></w:r></w:p>';
        $this->assertStringContainsString('adult', $this->processor->process($xmlNum, ['age' => 25]));
    }
}
