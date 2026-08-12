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
<div style="background-color:#E8721C; color:#fff; padding:6px 10px;"><span style="background-color:#1B4F8C; padding:1px 6px;">1</span>&nbsp; Informations générales</div>
<p>after</p>
</body></html>';

HtmlPdf::create()->loadHtml($html)->save(__DIR__ . '/sectionbar_test.pdf');
echo "done\n";
