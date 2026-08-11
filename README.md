# HtmlPdf

A dependency-free HTML/CSS → PDF renderer for PHP, built as a lightweight
alternative to dompdf. No Composer packages required — just PHP's built-in
`ext-dom`, `ext-mbstring`, `ext-iconv`. Because it's a **local path package**
(not pulled from Packagist), it is never subject to Composer's
`audit.block-insecure` check, so it can never reproduce the
`laravel/framework` advisory-blocking error you hit with dompdf.

## What it supports

- Tags: `div`, `p`, `span`, `h1`–`h6`, `table`/`thead`/`tbody`/`tr`/`th`/`td`
  (with `colspan`), `ul`/`ol`/`li`, `strong`/`b`, `em`/`i`, `br`, `hr`.
- CSS: inline `style="…"` attributes **and** a `<style>` block with
  tag / `.class` / `#id` selectors (no combinators, no pseudo-classes).
  Properties: `font-size`, `font-weight`, `font-style`, `font-family`
  (Helvetica/Times/Courier), `color`, `background-color`, `text-align`,
  `line-height`, `width`, `margin*`, `padding*`, `border`, `border-color`,
  `border-width`.
- Automatic page breaks for long tables/content, A4/Letter/A5/Legal paper,
  portrait/landscape.
- Full French accented-character support (é, è, à, ç, œ, «», etc.) via
  WinAnsiEncoding — no font embedding needed.

## What it does NOT support (by design, to keep this maintainable)

- Floats, absolute positioning, flexbox/grid.
- Images (`<img>`) — planned as a v2 addition if you need it.
- `rowspan`, nested tables.
- Table header repetition across page breaks (header only shows once, on
  the first page the table starts on).
- CSS combinators (`div > p`, `.a .b`), pseudo-classes, media queries.

If a document needs any of these, it'll silently ignore the unsupported
part rather than error — worth reviewing output for documents that push
past the supported subset above.

## Install into myliberte (Laravel)

1. Copy this `htmlpdf/` folder into your Laravel project, e.g. as
   `packages/htmlpdf/`.
2. Register it as a **path repository** in your app's `composer.json` —
   this is the key step that keeps it outside Packagist's audit database:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/htmlpdf" }
    ],
    "require": {
        "myliberte/htmlpdf": "*"
    }
}
```

3. `composer update myliberte/htmlpdf` — this will symlink the package in,
   with zero interaction with the advisory blocker.

## Usage

### Plain PHP

```php
use HtmlPdf\HtmlPdf;

$html = '<h1>Dossier Abonné</h1><p>...</p>';

HtmlPdf::create()
    ->setPaper('A4', 'portrait')
    ->loadHtml($html)
    ->save('/path/to/output.pdf');
```

### Laravel (dompdf-style)

```php
use HtmlPdf\Laravel\HtmlPdfFacade as HtmlPdf;

// From a Blade view, like Pdf::loadView() in barryvdh/laravel-dompdf
$pdf = HtmlPdf::loadView('subscribers.dossier-pdf', ['subscriber' => $subscriber]);
$pdf->setPaper('A4', 'portrait');

return response($pdf->output(), 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'inline; filename="dossier.pdf"',
]);

// or save to disk
$pdf->save(storage_path('app/dossiers/dossier-' . $subscriber->id . '.pdf'));
```

The service provider and facade auto-register via Composer's `extra.laravel`
block (package auto-discovery), same mechanism dompdf's Laravel wrapper uses.

## Testing

```bash
php test.php              # single-page letterhead + table sample
php test_pagebreak.php    # 60-row table, verifies page breaks
```

Both write a `.pdf` you can open directly, or inspect with:
```bash
pdftotext output.pdf -    # verify text extraction
pdftoppm -png -r 100 output.pdf preview   # render to PNG for visual check
```
