<?php

namespace Ayoratoumvone\Documentgeneratorx\Processors;

/**
 * Resolves conditional blocks in a DOCX document.
 *
 * Syntax (kept deliberately close to the {{name:type}} variable style):
 *
 *   {{if:age=A}}      shown when the `age` variable equals "A"
 *      ... block ...
 *   {{elseif:age=B}}  shown when `age` equals "B"
 *      ... block ...
 *   {{else}}          shown when nothing above matched
 *      ... block ...
 *   {{endif}}
 *
 * Conditions:
 *   {{if:flag}}          truthy   – flag is non-empty / true / non-zero / non-empty array
 *   {{if:age=A}}         equals   – (`=` and `==` are the same)
 *   {{if:age!=A}}        not equal
 *
 * The right-hand side is a literal string (optionally quoted); comparison is
 * case-sensitive. The block that "wins" is kept, every other branch — and all
 * of the markers themselves — are removed.
 *
 * Two layouts are supported and detected automatically:
 *   - Block:  every marker sits alone in its own paragraph. Whole paragraphs
 *             (and tables) between the markers are kept or dropped, and the
 *             marker paragraphs disappear entirely (no blank lines left behind).
 *   - Inline: the markers live inside a line of text, e.g.
 *             "Status: {{if:vip}}Gold{{else}}Standard{{endif}}." Only the text
 *             between the markers is kept or dropped.
 *
 * Nested conditionals are handled by resolving the innermost block first.
 */
class ConditionalProcessor
{
    /**
     * Resolve every conditional block using the provided variable values.
     */
    public function process(string $documentXml, array $variables): string
    {
        // Nothing to do if there is no opening marker at all.
        if (!preg_match('/\{\{\s*if\b/i', $documentXml)) {
            return $documentXml;
        }

        // Resolve one innermost {{if}}…{{endif}} per pass until none remain.
        $guard = 0;
        while ($guard++ < 5000) {
            [$documentXml, $changed] = $this->resolveInnermost($documentXml, $variables);
            if (!$changed) {
                break;
            }
        }

        return $documentXml;
    }

    /**
     * Find and resolve the first innermost conditional. Returns
     * [newXml, changed]; `changed` is false when nothing resolvable remains.
     */
    private function resolveInnermost(string $xml, array $variables): array
    {
        $markers = $this->findMarkers($xml);
        if (empty($markers)) {
            return [$xml, false];
        }

        // The first {{endif}} in document order …
        $endif = null;
        foreach ($markers as $i => $marker) {
            if ($marker['kw'] === 'endif') {
                $endif = $i;
                break;
            }
        }
        if ($endif === null) {
            return [$xml, false]; // opener without closer — leave for cleanup
        }

        // … paired with the nearest preceding {{if}} is an innermost block.
        $if = null;
        for ($i = $endif - 1; $i >= 0; $i--) {
            if ($markers[$i]['kw'] === 'if') {
                $if = $i;
                break;
            }
        }
        if ($if === null) {
            return [$xml, false]; // stray {{endif}}
        }

        $group       = array_slice($markers, $if, $endif - $if + 1); // if … endif
        $branchCount = count($group) - 1;                            // exclude endif

        // Pick the winning branch: first matching if/elseif, or else.
        $winner = -1;
        for ($i = 0; $i < $branchCount; $i++) {
            if ($group[$i]['kw'] === 'else') {
                $winner = $i;
                break;
            }
            if ($this->evaluate($group[$i]['cond'], $variables)) {
                $winner = $i;
                break;
            }
        }

        // Block mode only when every marker is alone in its own paragraph.
        [$opens, $closes] = $this->paragraphIndex($xml);
        $blockMode = true;
        foreach ($group as $marker) {
            [$pStart, $pEnd] = $this->enclosingParagraph($opens, $closes, $marker['start']);
            if ($pStart === null || !$this->aloneInParagraph($xml, $pStart, $pEnd, $marker['text'])) {
                $blockMode = false;
                break;
            }
        }

        if ($blockMode) {
            [$regionStart] = $this->enclosingParagraph($opens, $closes, $group[0]['start']);
            [, $regionEnd] = $this->enclosingParagraph($opens, $closes, $group[$branchCount]['start']);

            $content = '';
            if ($winner >= 0) {
                [, $winStart] = $this->enclosingParagraph($opens, $closes, $group[$winner]['start']);
                [$nextStart]  = $this->enclosingParagraph($opens, $closes, $group[$winner + 1]['start']);
                $content = substr($xml, $winStart, $nextStart - $winStart);
            }
        } else {
            $regionStart = $group[0]['start'];
            $regionEnd   = $group[$branchCount]['end'];

            $content = '';
            if ($winner >= 0) {
                $from    = $group[$winner]['end'];
                $to      = $group[$winner + 1]['start'];
                $content = substr($xml, $from, $to - $from);
            }
        }

        $xml = substr_replace($xml, $content, $regionStart, $regionEnd - $regionStart);

        return [$xml, true];
    }

    /**
     * Locate every conditional marker in document order.
     *
     * @return array<int, array{kw:string, cond:string, start:int, end:int, text:string}>
     */
    private function findMarkers(string $xml): array
    {
        $pattern = '/\{\{\s*(elseif|elif|else\s+if|else|endif|if)\s*(?::\s*([^{}]*?))?\s*\}\}/i';
        if (!preg_match_all($pattern, $xml, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $markers = [];
        foreach ($matches[0] as $i => $full) {
            $rawKw = strtolower(preg_replace('/\s+/', '', $matches[1][$i][0])); // e.g. "elseif", "if"
            $kw = match ($rawKw) {
                'if'    => 'if',
                'endif' => 'endif',
                'else'  => 'else',
                default => 'elseif', // elseif, elif, "else if"
            };

            $cond = ($matches[2][$i][1] ?? -1) >= 0 ? trim($matches[2][$i][0]) : '';

            $markers[] = [
                'kw'    => $kw,
                'cond'  => $cond,
                'start' => $full[1],
                'end'   => $full[1] + strlen($full[0]),
                'text'  => $full[0],
            ];
        }

        return $markers;
    }

    /**
     * Index of paragraph open/close tags (ascending by offset).
     *
     * @return array{0: array<int,array{start:int,end:int}>, 1: array<int,array{start:int,end:int}>}
     */
    private function paragraphIndex(string $xml): array
    {
        $opens = [];
        if (preg_match_all('/<w:p\b[^>]*>/', $xml, $mo, PREG_OFFSET_CAPTURE)) {
            foreach ($mo[0] as $o) {
                $opens[] = ['start' => $o[1], 'end' => $o[1] + strlen($o[0])];
            }
        }

        $closes = [];
        if (preg_match_all('/<\/w:p>/', $xml, $mc, PREG_OFFSET_CAPTURE)) {
            foreach ($mc[0] as $c) {
                $closes[] = ['start' => $c[1], 'end' => $c[1] + strlen($c[0])];
            }
        }

        return [$opens, $closes];
    }

    /**
     * The [start, end) of the paragraph enclosing $pos, or [null, null].
     *
     * @return array{0:?int, 1:?int}
     */
    private function enclosingParagraph(array $opens, array $closes, int $pos): array
    {
        $pStart = null;
        foreach ($opens as $open) {
            if ($open['start'] <= $pos) {
                $pStart = $open['start'];
            } else {
                break;
            }
        }

        $pEnd = null;
        foreach ($closes as $close) {
            if ($close['end'] > $pos) {
                $pEnd = $close['end'];
                break;
            }
        }

        return ($pStart === null || $pEnd === null) ? [null, null] : [$pStart, $pEnd];
    }

    /**
     * Does the paragraph contain nothing but this marker?
     */
    private function aloneInParagraph(string $xml, int $pStart, int $pEnd, string $markerText): bool
    {
        $paragraph = substr($xml, $pStart, $pEnd - $pStart);
        if (!preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $paragraph, $tm)) {
            return false;
        }

        return trim(implode('', $tm[1])) === trim($markerText);
    }

    /**
     * Evaluate a condition string against the variables.
     */
    private function evaluate(string $condition, array $variables): bool
    {
        $condition = trim($condition);
        if ($condition === '') {
            return false;
        }

        // Order matters: "!=" must be tested before "=".
        foreach (['!=' => false, '==' => true, '=' => true] as $operator => $wantEqual) {
            $pos = strpos($condition, $operator);
            if ($pos === false) {
                continue;
            }

            $name     = trim(substr($condition, 0, $pos));
            $expected = $this->unquote(trim(substr($condition, $pos + strlen($operator))));
            $actual   = $this->stringify($variables[$name] ?? null);

            return $wantEqual ? ($actual === $expected) : ($actual !== $expected);
        }

        // No operator → truthy test on the named variable.
        return $this->truthy($variables[$condition] ?? null);
    }

    /**
     * Strip one layer of matching surrounding quotes.
     */
    private function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last  = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    /**
     * Stringify a value for equality comparison (matches the scalar replacer).
     */
    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            return ''; // arrays only make sense for a truthy test
        }

        return (string) $value;
    }

    /**
     * Truthiness test for a bare {{if:flag}} condition.
     */
    private function truthy(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) > 0;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return false;
        }

        $string = trim((string) $value);

        return $string !== '' && $string !== '0';
    }
}
