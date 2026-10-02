<?php

/**
 * Renders tests/images-output.pdf exercising <img> support:
 * block / inline / centered images, sizing, transparency, table cells,
 * data URIs, every supported image format, failures and page breaks.
 *
 *   php test_images.php
 */

require __DIR__ . '/src/Font/Metrics.php';
require __DIR__ . '/src/Image/Image.php';
require __DIR__ . '/src/Image/ImageLoader.php';
require __DIR__ . '/src/Pdf/Writer.php';
require __DIR__ . '/src/Pdf/ContentStream.php';
require __DIR__ . '/src/Html/Style.php';
require __DIR__ . '/src/Html/Node.php';
require __DIR__ . '/src/Html/CssParser.php';
require __DIR__ . '/src/Html/Parser.php';
require __DIR__ . '/src/Layout/Engine.php';
require __DIR__ . '/src/HtmlPdf.php';

use HtmlPdf\HtmlPdf;

$fixtures = __DIR__ . '/tests/fixtures';
$dataUri = 'data:image/png;base64,' . base64_encode(file_get_contents("$fixtures/rgb.png"));

$formats = [
    'rgb.png' => 'PNG RGB',
    'rgba.png' => 'PNG RGBA',
    'palette.png' => 'PNG palette',
    'palette_trns.png' => 'PNG palette+tRNS',
    'gray.png' => 'PNG gray',
    'gray_alpha.png' => 'PNG gray+alpha',
    'interlaced_rgba.png' => 'PNG interlaced (GD)',
    'photo.jpg' => 'JPEG',
    'progressive.jpg' => 'JPEG progressive',
    'cmyk.jpg' => 'JPEG CMYK',
    'gray.jpg' => 'JPEG gray',
    'anim.gif' => 'GIF (GD)',
    'pic.webp' => 'WebP (GD)',
    'pic.bmp' => 'BMP (GD)',
];

$cells = '';
$row = '';
$i = 0;
foreach ($formats as $file => $label) {
    $row .= '<td style="width: 25%; background-color: #cccccc; font-size: 8px;"><img src="' . $file . '" style="width: 100px;"><br>' . $label . '</td>';
    if (++$i % 4 === 0) {
        $cells .= "<tr>$row</tr>";
        $row = '';
    }
}
if ($row !== '') {
    $cells .= "<tr>$row<td></td><td></td></tr>";
}

$filler = str_repeat('<p>Paragraphe de remplissage pour forcer un saut de page avant la grande image qui suit, avec des accents é è à ç.</p>', 22);

$html = <<<HTML
<html><head><style>
  h1 { font-size: 18px; }
  .logo { width: 120px; }
  .grey { background-color: #cccccc; }
  .center { text-align: center; }
</style></head><body>
<h1>Test des images</h1>

<p>1. Image bloc à taille naturelle (200x80 px = 150x60 pt) :</p>
<img src="rgb.png">

<p>2. Centrée, via text-align sur le conteneur :</p>
<div class="center"><img class="logo" src="rgb.png"></div>

<p>3. Attributs width="50%" puis height="30" (ratio conservé) :</p>
<img src="rgb.png" width="50%">
<img src="rgb.png" height="30">

<p>4. PNG RGBA (coin haut-gauche transparent, bas-droite à 50 %) sur fond gris :</p>
<div class="grey" style="padding: 6px;"><img src="rgba.png" style="width: 160px;"></div>

<p>5. Dans une phrase : avant <img src="rgb.png" style="height: 16px;"> après, puis <img src="rgb.png" style="height: 40px;"> une image plus haute que le texte, et la suite du texte qui continue sur la ligne suivante pour vérifier le retour à la ligne.</p>

<p>6. Dans un tableau (logo + texte, comme un en-tête) :</p>
<table>
  <tr>
    <td style="width: 90px;"><img src="rgb.png" style="width: 80px;"></td>
    <td><b>MOOV MONEY</b><br>Direction des Systèmes d'Information</td>
    <td style="width: 90px;" class="center"><img src="$dataUri" style="width: 60px;"></td>
  </tr>
</table>

<p>7. Image data: URI ci-dessus (colonne de droite), image introuvable avec alt, et sans alt :</p>
<img src="inexistant.png" alt="[logo manquant]">
<img src="inexistant.png">
<img src="http://example.com/logo.png" alt="[distant désactivé]">

<p>8. Formats supportés :</p>
<table>$cells</table>

$filler

<p>9. Très grande image (1500x1000 px), réduite à la largeur de la page :</p>
<img src="big_rgba.png">
<p>Fin du document.</p>
</body></html>
HTML;

$pdf = HtmlPdf::create()
    ->setBasePath($fixtures)
    ->setPaper('A4', 'portrait')
    ->loadHtml($html);

$t = microtime(true);
$pdf->save(__DIR__ . '/tests/images-output.pdf');
printf("OK, wrote %d bytes in %d ms\n", filesize(__DIR__ . '/tests/images-output.pdf'), (microtime(true) - $t) * 1000);

foreach ($pdf->getImageErrors() as $error) {
    echo "image error: $error\n";
}
