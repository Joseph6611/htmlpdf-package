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

$html = '<html><head><style>
.section-bar { background-color: #E8721C; color: #ffffff; font-size: 12px; font-weight: bold; padding: 6px 10px; margin-top: 16px; margin-bottom: 10px; }
.section-num { background-color: #1B4F8C; color: #ffffff; font-weight: bold; padding: 1px 6px; }
</style></head><body>
<div class="section-bar"><span class="section-num">1</span>&nbsp; Informations générales</div>
<p>after</p>
</body></html>';

HtmlPdf::create()->loadHtml($html)->save(__DIR__ . '/sectionbar_test2.pdf');
echo "done\n";
