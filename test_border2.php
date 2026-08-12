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
<table style="width:100%;">
<tr>
  <td style="width:170px; border:none; padding:2px 4px 6px 0;">Nom et Prénom(s) :</td>
  <td style="border:none; border-bottom: 1px solid #333; padding:2px 4px 4px 4px;">Koffi Adjovi</td>
</tr>
<tr>
  <td style="width:170px; border:none; padding:2px 4px 6px 0;">Téléphone :</td>
  <td style="border:none; border-bottom: 1px solid #333; padding:2px 4px 4px 4px;">+229 97 12 34 56</td>
</tr>
</table>
</body></html>';

HtmlPdf::create()->loadHtml($html)->save(__DIR__ . '/border_test2.pdf');
echo "done\n";
