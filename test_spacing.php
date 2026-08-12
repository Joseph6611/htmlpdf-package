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

$html = '<html><body><p style="font-weight: bold;">Je sollicite un agrément en tant que :</p></body></html>';
HtmlPdf::create()->loadHtml($html)->save(__DIR__ . '/spacing_test.pdf');
