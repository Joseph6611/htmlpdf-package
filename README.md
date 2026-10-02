# HtmlPdf

A dependency-free HTML/CSS → PDF renderer for PHP, built as a lightweight
alternative to dompdf. No Composer packages required — just PHP's built-in
`ext-dom`, `ext-mbstring`, `ext-iconv`. Because it's a **local path package**
(not pulled from Packagist), it is never subject to Composer's
`audit.block-insecure` check, so it can never reproduce the
`laravel/framework` advisory-blocking error you hit with dompdf.

## What it supports

- Tags: `div`, `p`, `span`, `h1`–`h6`, `table`/`thead`/`tbody`/`tr`/`th`/`td`
  (with `colspan`), `ul`/`ol`/`li`, `strong`/`b`, `em`/`i`, `br`, `hr`, `img`.
- CSS: inline `style="…"` attributes **and** a `<style>` block with
  tag / `.class` / `#id` selectors (no combinators, no pseudo-classes).
  Properties: `font-size`, `font-weight`, `font-style`, `font-family`
  (Helvetica/Times/Courier), `color`, `background-color`, `text-align`,
  `line-height`, `width`, `height` (images), `margin*`, `padding*`, `border`, `border-color`,
  `border-width`.
- Automatic page breaks for long tables/content, A4/Letter/A5/Legal paper,
  portrait/landscape.
- Full French accented-character support (é, è, à, ç, œ, «», etc.) via
  WinAnsiEncoding — no font embedding needed.

## Images

```html
<img src="logo.png" style="width: 120px;">
<img src="data:image/png;base64,iVBORw0KGgo..." width="80">
<div style="text-align: center;"><img src="/img/stamp.jpg" height="60"></div>
<td><img src="logo.png" style="width: 70px;"> <b>MOOV MONEY</b></td>
```

- **Sources**: local files, `data:` URIs, and (opt-in) `http(s)://` URLs.
- **Sizing**: `width` / `height` as an attribute (pixels or `%`) or CSS
  (`px`, `pt`, `em`, `%` for width). With only one of them, the aspect ratio is
  kept. With neither, the image is drawn at its natural size (96 dpi). It is
  always shrunk (ratio kept) to fit the available width and a full page.
- **Placement**: images are inline and sit on the text baseline, so they flow
  with text, wrap to the next line, respect `text-align` of their container,
  work inside table cells, and honor `margin*` on the `<img>`. A line grows
  to fit a tall image.
  Directly under `<body>`, each `<img>` starts its own line — wrap images in a
  `<div>`/`<p>` to put several on the same line.
- **Formats**: JPEG (including progressive, grayscale, CMYK) and PNG (all
  colour types, palette transparency, alpha channel) are handled natively.
  GIF, WebP, BMP, interlaced PNG and 16-bit PNG with alpha are converted
  through `ext-gd` when it is installed.
- **Errors never break the PDF**: an image that can't be loaded is replaced by
  its `alt` text (or skipped). Check `$pdf->getImageErrors()` after rendering.
- **Performance**: JPEG and opaque PNG are embedded as-is (instant). PNGs with
  an alpha channel are decoded in PHP: about 1 s for a 1500×1000 image, so
  resize large transparent PNGs when you can. The same image used several
  times is embedded once.

```php
use HtmlPdf\HtmlPdf;

HtmlPdf::create()
    ->setBasePath(public_path())      // where <img src="img/logo.png"> is looked up
    ->loadHtml($html)
    ->save($path);

// Laravel: asset('x.png') gives an http URL, which is blocked by default.
// Use a path under the base path, or embed the file:
$logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('img/logo.png')));
```

### Security

If any part of the HTML can come from users:

- Call `setBasePath()`: local images are then only read from inside that
  directory (`../` and symlinks pointing outside are refused).
- Leave `allowRemoteImages()` off (the default). Downloading URLs taken from
  untrusted HTML allows SSRF (requests to internal services or cloud metadata
  endpoints). Other schemes (`php://`, `phar://`, `ftp://`) are always refused.
- Files over 10 MB are refused.

## What it does NOT support (by design, to keep this maintainable)

- Floats, absolute positioning, flexbox/grid.
- Images as CSS backgrounds, SVG images, `float`ed / `object-fit` images.
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
php test_images.php       # images: sizes, alignment, transparency, formats, tables
```

Both write a `.pdf` you can open directly, or inspect with:
```bash
pdftotext output.pdf -    # verify text extraction
pdftoppm -png -r 100 output.pdf preview   # render to PNG for visual check
```
