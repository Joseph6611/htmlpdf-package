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

$html = <<<HTML
<html>
<head>
<style>
  body { font-family: Helvetica; }
  .header { text-align: center; margin-bottom: 10px; }
  .title { font-size: 16px; font-weight: bold; }
  .subtitle { font-size: 10px; color: #555555; }
  table { width: 100%; }
  th { background-color: #2c3e50; color: #ffffff; }
  td { font-size: 10px; }
  .status-ok { color: #1a7f37; font-weight: bold; }
  .status-pending { color: #b45309; font-weight: bold; }
  .footer { font-size: 9px; color: #888888; text-align: center; margin-top: 20px; }
</style>
</head>
<body>
  <div class="header">
    <div class="title">MYLIBERTE — Dossier Abonnés</div>
    <div class="subtitle">Rapport généré le 08/08/2026</div>
  </div>
  <hr />
  <p>Ce document récapitule l'état de validation des dossiers abonnés soumis par les agents de terrain, avec le statut courant de chaque dossier et les commentaires de rejet éventuels.</p>

  <table>
    <thead>
      <tr>
        <th style="width: 40px;">N°</th>
        <th>Nom de l'abonné</th>
        <th style="width: 90px;">MSISDN</th>
        <th style="width: 90px;">Statut</th>
        <th>Commentaire</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>1</td>
        <td>Koffi Adjovi</td>
        <td>+229 97 12 34 56</td>
        <td class="status-ok">Validé</td>
        <td>—</td>
      </tr>
      <tr>
        <td>2</td>
        <td>Chantal Hessou</td>
        <td>+229 96 45 67 89</td>
        <td class="status-pending">En attente</td>
        <td>En cours de validation niveau 2</td>
      </tr>
      <tr>
        <td>3</td>
        <td>Bienvenu Agossou</td>
        <td>+229 95 78 90 12</td>
        <td style="color:#b91c1c;font-weight:bold;">Rejeté</td>
        <td>Pièce d'identité illisible, à resoumettre avec une photo plus nette du document.</td>
      </tr>
    </tbody>
  </table>

  <p style="margin-top: 16px;">Ce rapport est généré automatiquement par le système de gestion des dossiers abonnés. Pour toute question, veuillez contacter l'équipe technique.</p>

  <div class="footer">MyLiberte — Document confidentiel — Page générée automatiquement</div>
</body>
</html>
HTML;

$pdf = HtmlPdf::create()->setPaper('A4', 'portrait')->loadHtml($html);
$pdf->save(__DIR__ . '/output.pdf');

echo "OK, wrote " . filesize(__DIR__ . '/output.pdf') . " bytes\n";
