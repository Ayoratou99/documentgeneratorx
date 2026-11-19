<?php

namespace Ayoratoumvone\Documentgeneratorx\Support;

/**
 * Plain text → content of a WordprocessingML <w:t> node.
 *
 * Inside <w:t>, a line feed or a tab is mere whitespace: Word and LibreOffice
 * collapse it, so "line 1\nline 2" would render on a single line. Word only
 * honours its own elements, <w:br/> and <w:tab/>, which are children of the
 * run (<w:r>), not of the text node.
 *
 * The returned string is meant to be inserted INSIDE an already open <w:t>:
 * each break closes the current text node, emits the element, and reopens a
 * text node in the same run, so the run's formatting (bold, size…) carries on.
 */
final class WordText
{
    public static function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $escaped = htmlspecialchars($text, ENT_XML1, 'UTF-8');

        return strtr($escaped, [
            "\n" => '</w:t><w:br/><w:t xml:space="preserve">',
            "\t" => '</w:t><w:tab/><w:t xml:space="preserve">',
        ]);
    }
}
