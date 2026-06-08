<?php

namespace Ayoratoumvone\Documentgeneratorx\Processors;

use Ayoratoumvone\Documentgeneratorx\Parser\VariableParser;

/**
 * Expands `{{name:array}}` placeholders.
 *
 * An array variable receives a list of values (`['A', 'B', 'C']`) and is
 * rendered VERTICALLY — one value per row, all in the same column.
 *
 * Two layouts are supported:
 *
 *  1. Inside a table cell (the common case). The table row that holds the
 *     placeholder is treated as a template and cloned once per value. When
 *     several array columns share the same row, the row is cloned to the
 *     length of the LONGEST array and shorter columns leave blank cells.
 *     Any blank rows the author drew directly under the template row are
 *     consumed first; when the data is longer than the drawn rows, extra
 *     rows are added automatically.
 *
 *  2. Outside a table (plain paragraph). The values are joined with line
 *     breaks so they stack inside the same paragraph.
 *
 * The class works on the raw `word/document.xml` string so it composes with
 * the rest of the DOCX pipeline (fragment repair, scalar replacement, …).
 */
class ArrayProcessor
{
    public function __construct(protected VariableParser $parser)
    {
    }

    /**
     * Replace every array placeholder found in the document.
     *
     * @param string $documentXml      Raw word/document.xml contents.
     * @param array  $templateVariables Parsed variables keyed by name (from VariableParser::parse).
     * @param array  $values            User-supplied values keyed by variable name.
     */
    public function process(string $documentXml, array $templateVariables, array $values): string
    {
        // Keep only the variables the template declared as arrays.
        $arrayVariables = array_filter(
            $templateVariables,
            fn (array $info) => ($info['type'] ?? null) === 'array'
        );

        if (empty($arrayVariables)) {
            return $documentXml;
        }

        // Coerce every value into a clean, zero-indexed list up front so the
        // row/inline expanders can share it without re-normalising.
        $lists = [];
        foreach ($arrayVariables as $name => $info) {
            $lists[$name] = $this->toList($values[$name] ?? null);
        }

        // Tables first (vertical row cloning), then whatever is left over in
        // ordinary paragraphs (multiline join).
        $documentXml = $this->expandTableRows($documentXml, $arrayVariables, $lists);
        $documentXml = $this->expandInline($documentXml, $arrayVariables, $lists);

        return $documentXml;
    }

    /**
     * Clone every table row that contains one or more array placeholders.
     */
    protected function expandTableRows(string $xml, array $arrayVariables, array $lists): string
    {
        // Capture each <w:tr>…</w:tr> with its byte offset. `\b` after "tr"
        // stops us matching <w:trPr> (row properties), and the literal
        // </w:tr> close never appears inside </w:trPr>.
        if (!preg_match_all('/<w:tr\b[^>]*>.*?<\/w:tr>/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
            return $xml;
        }

        $rows = [];
        foreach ($matches[0] as $match) {
            $rows[] = [
                'xml'   => $match[0],
                'start' => $match[1],
                'end'   => $match[1] + strlen($match[0]),
            ];
        }

        $edits = [];
        $count = count($rows);
        $i = 0;

        while ($i < $count) {
            $row = $rows[$i];

            // Which array columns live in this row?
            $columns = $this->arraysInRow($row['xml'], $arrayVariables);

            // Skip rows with no array placeholder. Also skip rows that embed a
            // nested table: our non-greedy row match would close on the inner
            // </w:tr>, so cloning such a row would corrupt the document.
            if (empty($columns) || strpos($row['xml'], '<w:tbl') !== false) {
                $i++;
                continue;
            }

            // Row height = the longest array among the columns in this row.
            $height = 0;
            foreach ($columns as $name) {
                $height = max($height, count($lists[$name]));
            }

            $expanded = '';
            for ($line = 0; $line < $height; $line++) {
                $clone = $row['xml'];
                foreach ($columns as $name) {
                    $info = $arrayVariables[$name];
                    $cell = array_key_exists($line, $lists[$name])
                        ? $this->renderValue($info, $lists[$name][$line])
                        : '';
                    $clone = preg_replace_callback(
                        $this->parser->getPlaceholderPattern($info),
                        fn () => $cell,
                        $clone
                    );
                }
                $expanded .= $clone;
            }

            // Swallow blank rows the author drew right below the template row
            // (within the same table) so the output is exactly $height rows.
            $spanEnd = $row['end'];
            $j = $i + 1;
            while ($j < $count) {
                $gap = substr($xml, $rows[$j - 1]['end'], $rows[$j]['start'] - $rows[$j - 1]['end']);
                if (strpos($gap, '</w:tbl>') !== false || strpos($gap, '<w:tbl') !== false) {
                    break; // crossed a table boundary
                }
                if (!$this->isEmptyRow($rows[$j]['xml'])) {
                    break; // hit real content
                }
                $spanEnd = $rows[$j]['end'];
                $j++;
            }

            $edits[] = [
                'start'       => $row['start'],
                'length'      => $spanEnd - $row['start'],
                'replacement' => $expanded,
            ];

            $i = $j; // continue after the consumed block
        }

        // Apply edits from the end so earlier offsets stay valid.
        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);
        foreach ($edits as $edit) {
            $xml = substr_replace($xml, $edit['replacement'], $edit['start'], $edit['length']);
        }

        return $xml;
    }

    /**
     * Replace any array placeholder left outside a table with a multiline run.
     */
    protected function expandInline(string $xml, array $arrayVariables, array $lists): string
    {
        foreach ($arrayVariables as $name => $info) {
            $pattern = $this->parser->getPlaceholderPattern($info);

            $parts = array_map(
                fn ($value) => htmlspecialchars($this->stringify($value), ENT_XML1, 'UTF-8'),
                $lists[$name]
            );
            $joined = implode('</w:t><w:br/><w:t xml:space="preserve">', $parts);

            $styles = $info['styles'] ?? [];
            if ($joined !== '' && !empty($styles)) {
                $styleXml = $this->parser->stylesToDocxXml($styles);
                $joined = '</w:t></w:r><w:r>' . $styleXml
                    . '<w:t xml:space="preserve">' . $joined
                    . '</w:t></w:r><w:r><w:t xml:space="preserve">';
            }

            $xml = preg_replace_callback($pattern, fn () => $joined, $xml);
        }

        return $xml;
    }

    /**
     * Names of the array variables whose placeholder appears in a row.
     *
     * @return string[]
     */
    protected function arraysInRow(string $rowXml, array $arrayVariables): array
    {
        $found = [];
        foreach ($arrayVariables as $name => $info) {
            if (preg_match($this->parser->getPlaceholderPattern($info), $rowXml)) {
                $found[] = $name;
            }
        }
        return $found;
    }

    /**
     * Render a single value as the inner replacement for a `<w:t>` node,
     * mirroring the scalar styling path so array cells look identical to
     * ordinary styled placeholders.
     */
    protected function renderValue(array $info, mixed $value): string
    {
        $text   = htmlspecialchars($this->stringify($value), ENT_XML1, 'UTF-8');
        $styles = $info['styles'] ?? [];

        if (empty($styles)) {
            return $text;
        }

        $styleXml = $this->parser->stylesToDocxXml($styles);

        return '</w:t></w:r><w:r>' . $styleXml
            . '<w:t xml:space="preserve">' . $text
            . '</w:t></w:r><w:r><w:t xml:space="preserve">';
    }

    /**
     * Normalise a raw value into a zero-indexed list of scalars.
     */
    protected function toList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(is_array($value) ? $value : [$value]);
    }

    /**
     * Stringify a scalar value the same way the scalar replacer does.
     */
    protected function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /**
     * A row is "empty" when it carries no placeholder and no visible text,
     * i.e. it is one of the blank rows an author drew to reserve space.
     */
    protected function isEmptyRow(string $rowXml): bool
    {
        if (strpos($rowXml, '{{') !== false) {
            return false;
        }

        $text = preg_replace('/<[^>]+>/', '', $rowXml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim($text) === '';
    }
}
