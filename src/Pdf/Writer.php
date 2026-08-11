<?php

namespace HtmlPdf\Pdf;

/**
 * Minimal, dependency-free PDF file writer.
 *
 * Builds a valid PDF from scratch: object table, page tree, content
 * streams, standard-14 font resources and cross-reference table.
 * No external libraries, no font embedding required.
 */
class Writer
{
    private array $objects = [];
    private int $nextId = 1;
    private array $pageIds = [];
    private array $fontIds = []; // fontName => objId
    private array $fontKeys = []; // fontName => /F1 style resource key
    private int $catalogId;
    private int $pagesId;
    private string $title = '';

    public function __construct(private float $pageWidth = 595.28, private float $pageHeight = 841.89)
    {
        $this->pagesId = $this->reserve();
        $this->catalogId = $this->reserve();
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    private function reserve(): int
    {
        $id = $this->nextId++;
        $this->objects[$id] = null;
        return $id;
    }

    private function set(int $id, string $content): void
    {
        $this->objects[$id] = $content;
    }

    public function fontResourceKey(string $fontName): string
    {
        if (isset($this->fontKeys[$fontName])) {
            return $this->fontKeys[$fontName];
        }

        $id = $this->reserve();
        $this->set($id, "<< /Type /Font /Subtype /Type1 /BaseFont /{$fontName} /Encoding /WinAnsiEncoding >>");
        $this->fontIds[$fontName] = $id;
        $key = 'F' . (count($this->fontKeys) + 1);
        $this->fontKeys[$fontName] = $key;

        return $key;
    }

    /**
     * Add a page with a raw content stream (already-built PDF operators).
     */
    public function addPage(string $contentStream): int
    {
        $contentId = $this->reserve();
        $len = strlen($contentStream);
        $this->set($contentId, "<< /Length {$len} >>\nstream\n{$contentStream}\nendstream");

        $pageId = $this->reserve();
        $resources = $this->buildResourcesDict();
        $w = $this->fmt($this->pageWidth);
        $h = $this->fmt($this->pageHeight);
        $this->set($pageId, "<< /Type /Page /Parent {$this->pagesId} 0 R /MediaBox [0 0 {$w} {$h}] "
            . "/Resources {$resources} /Contents {$contentId} 0 R >>");

        $this->pageIds[] = $pageId;

        return $pageId;
    }

    private function buildResourcesDict(): string
    {
        $fontEntries = [];
        foreach ($this->fontKeys as $fontName => $key) {
            $fontEntries[] = "/{$key} {$this->fontIds[$fontName]} 0 R";
        }
        $fonts = implode(' ', $fontEntries);

        return "<< /Font << {$fonts} >> >>";
    }

    public function fmt(float $n): string
    {
        // Trim trailing zeros to keep the file compact and avoid float noise
        $s = rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
        return $s === '' || $s === '-' ? '0' : $s;
    }

    public function output(): string
    {
        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $this->pageIds));
        $this->set($this->pagesId, "<< /Type /Pages /Kids [{$kids}] /Count " . count($this->pageIds) . " >>");

        $infoId = null;
        if ($this->title !== '') {
            $infoId = $this->reserve();
            $this->set($infoId, '<< /Title (' . $this->escape($this->title) . ') /Producer (HtmlPdf) >>');
        }

        $this->set($this->catalogId, "<< /Type /Catalog /Pages {$this->pagesId} 0 R >>");

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        for ($id = 1; $id < $this->nextId; $id++) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n{$this->objects[$id]}\nendobj\n";
        }

        $xrefStart = strlen($out);
        $count = $this->nextId;
        $out .= "xref\n0 {$count}\n";
        $out .= "0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $out .= "trailer\n<< /Size {$count} /Root {$this->catalogId} 0 R";
        if ($infoId) {
            $out .= " /Info {$infoId} 0 R";
        }
        $out .= " >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $out;
    }

    public function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->toPdfDocEncoding($s));
    }

    /**
     * Convert UTF-8 to WinAnsi (cp1252) byte-per-char, since Type1 standard
     * fonts expect single-byte text strings.
     */
    public function toPdfDocEncoding(string $utf8): string
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $utf8);
        return $converted !== false ? $converted : $utf8;
    }
}
