<?php

namespace HtmlPdf;

use HtmlPdf\Html\Parser;
use HtmlPdf\Image\ImageLoader;
use HtmlPdf\Layout\Engine;
use HtmlPdf\Pdf\Writer;

class HtmlPdf
{
    private string $html = '';
    private string $paperSize = 'A4';
    private string $orientation = 'portrait';
    private array $margins = ['top' => 50, 'right' => 45, 'bottom' => 50, 'left' => 45];
    private ?string $basePath = null;
    private bool $allowRemoteImages = false;
    private ?ImageLoader $imageLoader = null;

    public static function create(): self
    {
        return new self();
    }

    public function loadHtml(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    public function setPaper(string $size, string $orientation = 'portrait'): self
    {
        $this->paperSize = strtoupper($size);
        $this->orientation = $orientation;
        return $this;
    }

    public function setMargins(float $top, float $right, float $bottom, float $left): self
    {
        $this->margins = compact('top', 'right', 'bottom', 'left');
        return $this;
    }

    /**
     * Directory that <img src="..."> paths are resolved against. When set, local
     * images can only be read from inside it (use this whenever the HTML is not
     * fully trusted), e.g. ->setBasePath(public_path()).
     */
    public function setBasePath(?string $path): self
    {
        $this->basePath = $path;
        return $this;
    }

    /**
     * Allow <img src="http(s)://..."> to be downloaded. Off by default because
     * fetching URLs taken from untrusted HTML enables SSRF attacks.
     */
    public function allowRemoteImages(bool $allow = true): self
    {
        $this->allowRemoteImages = $allow;
        return $this;
    }

    /**
     * Images that could not be loaded during the last output()/save()/stream().
     * Such images are skipped (or replaced by their alt text) instead of
     * failing the whole document.
     *
     * @return string[]
     */
    public function getImageErrors(): array
    {
        return $this->imageLoader?->errors ?? [];
    }

    private function paperDimensions(): array
    {
        $sizes = [
            'A4' => [595.28, 841.89],
            'LETTER' => [612.0, 792.0],
            'A5' => [419.53, 595.28],
            'LEGAL' => [612.0, 1008.0],
        ];
        [$w, $h] = $sizes[$this->paperSize] ?? $sizes['A4'];

        return $this->orientation === 'landscape' ? [$h, $w] : [$w, $h];
    }

    public function output(): string
    {
        [$pageWidth, $pageHeight] = $this->paperDimensions();

        $writer = new Writer($pageWidth, $pageHeight);

        $parser = new Parser();
        $root = $parser->parse($this->html);

        $this->imageLoader = new ImageLoader($this->basePath, $this->allowRemoteImages);

        $engine = new Engine(
            $writer,
            $pageWidth,
            $pageHeight,
            $this->margins['top'],
            $this->margins['right'],
            $this->margins['bottom'],
            $this->margins['left'],
            $this->imageLoader,
        );

        $pages = $engine->render($root);
        foreach ($pages as $page) {
            $writer->addPage($page->raw());
        }

        return $writer->output();
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->output());
    }

    /** Stream the PDF to the browser, dompdf-style. */
    public function stream(string $filename = 'document.pdf'): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $this->output();
    }

    public function download(string $filename = 'document.pdf'): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->output();
    }
}
