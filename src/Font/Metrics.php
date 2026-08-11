<?php

namespace HtmlPdf\Font;

/**
 * Character width tables (in 1/1000 em units) for the PDF standard 14 fonts,
 * using WinAnsiEncoding (covers Latin-1 / French accented characters).
 *
 * These are the font's intrinsic metrics (part of the PDF spec's standard
 * font set) - no font embedding is required to use them.
 */
class Metrics
{
    /** @var array<string,array<int,int>> */
    private static array $widths = [];

    public static function width(string $font, string $char): int
    {
        $table = self::table($font);
        $code = mb_ord($char, 'UTF-8');
        if ($code === false) {
            return 556;
        }

        return $table[$code] ?? ($code > 255 ? 600 : 556);
    }

    public static function stringWidth(string $font, string $text, float $fontSize): float
    {
        $total = 0;
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $total += self::width($font, mb_substr($text, $i, 1, 'UTF-8'));
        }

        return ($total / 1000) * $fontSize;
    }

    private static function table(string $font): array
    {
        if (isset(self::$widths[$font])) {
            return self::$widths[$font];
        }

        $isBold = str_contains($font, 'Bold');
        $isTimes = str_starts_with($font, 'Times');
        $isMono = str_starts_with($font, 'Courier');

        if ($isMono) {
            // Fixed pitch
            $table = array_fill(32, 224, 600);
            return self::$widths[$font] = $table;
        }

        // Base widths for common ASCII punctuation/letters, approximated
        // from the standard Helvetica/Times AFM metrics.
        $narrow = ['i' => 1, 'l' => 1, 'I' => 1, 'j' => 1, 'f' => 1, 't' => 1, '.' => 1, ',' => 1, "'" => 1, '!' => 1, ':' => 1, ';' => 1, '|' => 1, '(' => 1, ')' => 1, '[' => 1, ']' => 1, ' ' => 1];
        $wide = ['m' => 1, 'w' => 1, 'M' => 1, 'W' => 1, '@' => 1];

        $helveticaBase = [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
            '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
            '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
            '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
            '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
            'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
            'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556,
            '`' => 333, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
            'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
            'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
            'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        ];

        $timesBase = [
            ' ' => 250, '!' => 333, '"' => 408, '#' => 500, '$' => 500, '%' => 833, '&' => 778, "'" => 180,
            '(' => 333, ')' => 333, '*' => 500, '+' => 564, ',' => 250, '-' => 333, '.' => 250, '/' => 278,
            '0' => 500, '1' => 500, '2' => 500, '3' => 500, '4' => 500, '5' => 500, '6' => 500, '7' => 500,
            '8' => 500, '9' => 500, ':' => 278, ';' => 278, '<' => 564, '=' => 564, '>' => 564, '?' => 444,
            '@' => 921, 'A' => 722, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 611, 'F' => 556, 'G' => 722,
            'H' => 722, 'I' => 333, 'J' => 389, 'K' => 722, 'L' => 611, 'M' => 889, 'N' => 722, 'O' => 722,
            'P' => 556, 'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611, 'U' => 722, 'V' => 722, 'W' => 944,
            'X' => 722, 'Y' => 722, 'Z' => 611, '[' => 333, '\\' => 278, ']' => 333, '^' => 469, '_' => 500,
            '`' => 333, 'a' => 444, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444, 'f' => 333, 'g' => 500,
            'h' => 500, 'i' => 278, 'j' => 278, 'k' => 500, 'l' => 278, 'm' => 778, 'n' => 500, 'o' => 500,
            'p' => 500, 'q' => 500, 'r' => 333, 's' => 389, 't' => 278, 'u' => 500, 'v' => 500, 'w' => 722,
            'x' => 500, 'y' => 500, 'z' => 444, '{' => 480, '|' => 200, '}' => 480, '~' => 541,
        ];

        $base = $isTimes ? $timesBase : $helveticaBase;

        if ($isBold) {
            foreach ($base as $ch => $w) {
                $base[$ch] = (int) round($w * 1.06);
            }
        }

        $table = [];
        foreach ($base as $ch => $w) {
            $table[ord($ch)] = $w;
        }

        // Common accented (WinAnsi / Latin-1) chars used in French text -
        // approximated to their unaccented base letter's width.
        $accented = [
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Î' => 'I', 'Ï' => 'I', 'î' => 'i', 'ï' => 'i',
            'Ô' => 'O', 'Ö' => 'O', 'ô' => 'o', 'ö' => 'o',
            'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'C', 'ç' => 'c', 'Ñ' => 'N', 'ñ' => 'n',
            'œ' => 'oe', 'Œ' => 'OE', '«' => '"', '»' => '"', '’' => "'", '‘' => "'",
            '…' => '...', '°' => 'o',
        ];
        foreach ($accented as $ch => $like) {
            $code = mb_ord($ch, 'UTF-8');
            $likeWidth = 0;
            foreach (mb_str_split($like) as $c) {
                $likeWidth += $base[$c] ?? 556;
            }
            $table[$code] = $likeWidth;
        }

        // Dashes have their own glyph widths (roughly 1 em / 0.6 em), not a hyphen's.
        $table[mb_ord('–', 'UTF-8')] = $isBold ? (int) round(600 * 1.06) : 600; // en dash
        $table[mb_ord('—', 'UTF-8')] = $isBold ? (int) round(1000 * 1.06) : 1000; // em dash

        return self::$widths[$font] = $table;
    }
}
