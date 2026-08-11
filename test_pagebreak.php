<?php
require __DIR__ . "/src/Font/Metrics.php";
require __DIR__ . "/src/Pdf/Writer.php";
require __DIR__ . "/src/Pdf/ContentStream.php";
require __DIR__ . "/src/Html/Style.php";
require __DIR__ . "/src/Html/Node.php";
require __DIR__ . "/src/Html/CssParser.php";
require __DIR__ . "/src/Html/Parser.php";
require __DIR__ . "/src/Layout/Engine.php";
require __DIR__ . "/src/HtmlPdf.php";

use HtmlPdf\HtmlPdf;

$rows = '';
for ($i = 1; $i <= 60; $i++) {
    $rows .= "<tr><td>{$i}</td><td>Abonné Test {$i}</td><td>+229 90 00 00 " . str_pad($i, 2, '0', STR_PAD_LEFT) . "</td><td>Validé</td></tr>";
}

$html = <<<HTML
<html><body>
<h1>Liste complète des abonnés</h1>
<table>
<thead><tr><th>N°</th><th>Nom</th><th>MSISDN</th><th>Statut</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
</body></html>
HTML;

HtmlPdf::create()->loadHtml($html)->save(__DIR__ . '/output_multipage.pdf');
echo "done\n";
