<?php
require __DIR__ . '/src/Font/Metrics.php';
require __DIR__ . '/src/Pdf/Writer.php';
require __DIR__ . '/src/Pdf/ContentStream.php';
require __DIR__ . '/src/Html/Style.php';
require __DIR__ . '/src/Html/Node.php';
require __DIR__ . '/src/Html/CssParser.php';
require __DIR__ . '/src/Html/Parser.php';
require __DIR__ . '/src/Layout/Engine.php';
require __DIR__ . '/src/HtmlPdf.php';

use HtmlPdf\HtmlPdf;

$html = '<html><body>
<div style="margin-bottom:14px;">
  <span>Nom et Prénom(s) :</span>
  <span style="border-bottom: 1px solid #000; padding-left: 4px;">Koffi Adjovi</span>
</div>
</body></html>';

HtmlPdf::create()->loadHtml($html)->save(__DIR__ . '/border_test.pdf');
echo "done\n";
