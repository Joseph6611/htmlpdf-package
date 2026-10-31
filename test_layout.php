<?php

/**
 * Checks the layout features added in 1.2 (page breaks, underline, rounded boxes,
 * vertical-align, inheritance fixes). Run: php test_layout.php
 */
foreach (['Font/Metrics', 'Image/Image', 'Image/ImageLoader', 'Pdf/Writer', 'Pdf/ContentStream', 'Html/Style', 'Html/Node', 'Html/CssParser', 'Html/Parser', 'Layout/Engine'] as $f) {
    require __DIR__ . "/src/$f.php";
}
require __DIR__ . '/src/HtmlPdf.php';

use HtmlPdf\HtmlPdf;

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail) {
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    $fail += $ok ? 0 : 1;
};
$pages = fn (string $pdf) => preg_match_all('#/Type /Page /#', $pdf);
$render = fn (string $html) => HtmlPdf::create()->loadHtml($html)->output();

// page-break-before / after
$pdf = $render('<p>A</p><div style="page-break-before: always;"></div><p>B</p><p style="page-break-after: always;">C</p><p>D</p>');
$check('page-break-before + after give 3 pages', $pages($pdf) === 3);
$check('no empty trailing page after a final page-break-after', $pages($render('<p style="page-break-after: always;">A</p>')) === 1);

// underline: <u> and text-decoration draw a line operator
$check('<u> draws an underline', substr_count($render('<p><u>abc</u></p>'), " m ") + substr_count($render('<p><u>abc</u></p>'), ' l S') > 0
    && str_contains($render('<p><u>abc</u></p>'), ' l S'));
$check('no underline without <u>', !str_contains($render('<p>abc</p>'), ' l S'));

// rounded background uses Bezier curves
$check('border-radius paints curves', str_contains($render('<div style="background-color:#ccc; border-radius:10px; padding:5px;">x</div>'), ' c '));
$check('square background has no curves', !str_contains($render('<div style="background-color:#ccc; padding:5px;">x</div>'), ' c '));

// table width must not leak into cells: the right-hand cell stays inside the page
preg_match_all('#([\d.]+) ([\d.]+) Td\n\(Right\)#', $render('<table style="width:100%"><tr><td style="width:60%">L</td><td style="text-align:right">Right</td></tr></table>'), $m);
$check('right-aligned cell ends inside the page (no width inheritance)', !empty($m[1]) && (float) $m[1][0] < 595);

// body rules are applied
$check('body font-size is applied', str_contains($render('<style>body{font-size:20px;}</style><p>Hello</p>'), ' 15 Tf'));

// bold metrics are exact
$check('Helvetica-Bold width of "Informations générales" = 11003/1000 em', (int) round(\HtmlPdf\Font\Metrics::stringWidth('Helvetica-Bold', 'Informations générales', 1000)) === 11003);

exit($fail > 0 ? 1 : 0);
