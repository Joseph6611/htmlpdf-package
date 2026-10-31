<?php

namespace HtmlPdf\Pdf;

use RuntimeException;

/**
 * Combines finished PDF files: merge, stamp one PDF over another, count pages.
 *
 * HtmlPdf only *writes* PDFs. Reading existing ones (scanned RCCM extracts,
 * a static terms-and-conditions file, ...) needs a real PDF parser, and the
 * robust free option is the `qpdf` command line tool (sudo apt install qpdf).
 * It never re-encodes pages, so merged pages keep their exact quality.
 *
 * No shell is involved: commands are passed to proc_open() as argument arrays.
 */
class Assembler
{
    public function __construct(
        private string $qpdf = 'qpdf',
        private string $pdftoppm = 'pdftoppm',
    ) {
    }

    public function hasQpdf(): bool
    {
        return $this->run([$this->qpdf, '--version'], [0]) !== null;
    }

    public function hasPdftoppm(): bool
    {
        return $this->run([$this->pdftoppm, '-v'], [0, 99]) !== null;
    }

    public function pageCount(string $pdf): int
    {
        $out = $this->run([$this->qpdf, '--show-npages', $pdf], [0, 3]);
        if ($out === null || !ctype_digit(trim($out))) {
            throw new RuntimeException("Cannot read PDF (qpdf failed): {$pdf}");
        }
        return (int) trim($out);
    }

    /**
     * Concatenate PDFs in order. Each item is a path, or [path, 'pages'] where
     * pages is a qpdf range such as '1-2' or '3,z' ('z' = last page).
     *
     * @param array<int,string|array{0:string,1:string}> $inputs
     */
    public function merge(array $inputs, string $output): void
    {
        $args = [$this->qpdf, '--empty', '--pages'];
        foreach ($inputs as $item) {
            [$path, $range] = is_array($item) ? $item : [$item, '1-z'];
            $args[] = $path;
            $args[] = $range;
        }
        $args[] = '--';
        $args[] = $output;

        if ($this->run($args, [0, 3]) === null) {
            throw new RuntimeException('qpdf could not merge the PDF files');
        }
    }

    /**
     * Draw page 1 of $stamp on top of the given pages of $base (default: all).
     * Typical use: add per-client data to a static template.
     *
     * @param string|null $onPages qpdf page range of $base, e.g. '4' or '2-z'
     */
    public function overlay(string $base, string $stamp, string $output, ?string $onPages = null): void
    {
        $args = [$this->qpdf, $base, '--overlay', $stamp, '--from=1', '--repeat=1'];
        if ($onPages !== null) {
            $args[] = '--to=' . $onPages;
        }
        $args[] = '--';
        $args[] = $output;

        if ($this->run($args, [0, 3]) === null) {
            throw new RuntimeException('qpdf could not overlay the PDF files');
        }
    }

    /**
     * Fallback when qpdf is missing: turn every page into a JPEG (poppler's
     * pdftoppm) that can be placed with <img>. Text stays sharp enough for
     * scans, but is no longer selectable and the file gets bigger.
     *
     * @return string[] absolute paths of the page images, in order
     */
    public function rasterize(string $pdf, string $outDir, int $dpi = 130, string $prefix = 'page'): array
    {
        $base = rtrim($outDir, '/') . '/' . $prefix;
        if ($this->run([$this->pdftoppm, '-jpeg', '-jpegopt', 'quality=82', '-r', (string) $dpi, $pdf, $base], [0]) === null) {
            throw new RuntimeException('pdftoppm could not read the PDF file');
        }
        $files = glob($base . '-*.jpg') ?: [];
        natsort($files);

        return array_values($files);
    }

    /** @return string|null stdout, or null when the command failed or is missing */
    private function run(array $command, array $okCodes): ?string
    {
        $proc = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return null;
        }
        $out = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return in_array($code, $okCodes, true) ? (string) $out : null;
    }
}
