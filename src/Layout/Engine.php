<?php

namespace HtmlPdf\Layout;

use HtmlPdf\Font\Metrics;
use HtmlPdf\Html\Node;
use HtmlPdf\Html\Style;
use HtmlPdf\Pdf\ContentStream;
use HtmlPdf\Pdf\Writer;

class Engine
{
    private array $pages = []; // ContentStream[]
    private ?ContentStream $stream = null;
    private float $y = 0; // top-down cursor, 0 = top of content area
    private array $repeatingHeaderRows = []; // rows to redraw at top of a new page (table thead)

    public function __construct(
        private Writer $writer,
        private float $pageWidth,
        private float $pageHeight,
        private float $marginTop = 50,
        private float $marginRight = 45,
        private float $marginBottom = 50,
        private float $marginLeft = 45,
    ) {
    }

    public function contentWidth(): float
    {
        return $this->pageWidth - $this->marginLeft - $this->marginRight;
    }

    /** @return ContentStream[] */
    public function render(Node $root): array
    {
        $this->newPage();
        $this->layoutBlockChildren($root, $this->marginLeft, $this->contentWidth());

        return $this->pages;
    }

    private function newPage(): void
    {
        $this->stream = new ContentStream($this->writer);
        $this->pages[] = $this->stream;
        $this->y = $this->marginTop;
    }

    private function pdfY(float $topY): float
    {
        return $this->pageHeight - $topY;
    }

    /** Ensure at least $needed points remain before the bottom margin; break page if not. */
    private function ensureSpace(float $needed): void
    {
        $bottom = $this->pageHeight - $this->marginBottom;
        if ($this->pdfY($this->y) - $needed < $this->marginBottom) {
            $this->newPage();
        }
    }

    private function layoutBlockChildren(Node $parent, float $x, float $width): void
    {
        foreach ($parent->children as $child) {
            if ($child->isText()) {
                $this->layoutParagraphRun([$child], $x, $width);
                continue;
            }

            switch ($child->tag) {
                case 'table':
                    $this->layoutTable($child, $x, $width);
                    break;
                case 'hr':
                    $this->layoutHr($child, $x, $width);
                    break;
                case 'ul':
                case 'ol':
                    $this->layoutList($child, $x, $width);
                    break;
                case 'br':
                    $this->y += $child->style->fontSize * $child->style->lineHeight;
                    break;
                default:
                    $this->y += $child->style->marginTop;
                    if ($this->isInlineOnly($child)) {
                        $this->layoutBoxedInline($child, $x, $width);
                    } else {
                        $this->layoutBoxed($child, $x, $width);
                    }
                    $this->y += $child->style->marginBottom;
            }
        }
    }

    private function isInlineOnly(Node $node): bool
    {
        foreach ($node->children as $c) {
            if (!$c->isText() && !in_array($c->tag, ['strong', 'b', 'em', 'i', 'span', 'br'], true)) {
                return false;
            }
        }
        return true;
    }

    /** Flatten a block node's inline descendants into a run of styled text nodes. */
    private function flattenInline(Node $node): array
    {
        $runs = [];
        foreach ($node->children as $c) {
            if ($c->isText()) {
                $t = clone $c;
                $t->style = $node->style->clone();
                $t->style->bold = $node->style->bold;
                $runs[] = $t;
            } elseif ($c->tag === 'br') {
                $br = new Node('br', $node->style);
                $runs[] = $br;
            } else {
                foreach ($this->flattenInline($c) as $sub) {
                    $runs[] = $sub;
                }
            }
        }
        // Wrap text nodes so paragraph-level style (from $node) applies as base,
        // but inner styled runs (strong/em) keep their own bold/italic.
        return $runs;
    }

    private function layoutBoxed(Node $node, float $x, float $width): void
    {
        $style = $node->style;
        $innerWidth = $width - $style->paddingLeft - $style->paddingRight;
        $innerX = $x + $style->paddingLeft;

        $boxTop = $this->y;
        $this->y += $style->paddingTop;

        $streamBefore = $this->stream;
        $mark = $streamBefore->mark();
        $streamBefore->invalidateColorCache();

        $this->layoutBlockChildren($node, $innerX, $innerWidth);

        $this->y += $style->paddingBottom;
        $boxHeight = $this->y - $boxTop;

        $this->spliceBoxDecorations($style, $x, $boxTop, $width, $boxHeight, $streamBefore, $mark);
    }

    private function layoutBoxedInline(Node $node, float $x, float $width): void
    {
        $style = $node->style;
        $innerWidth = $width - $style->paddingLeft - $style->paddingRight;
        $innerX = $x + $style->paddingLeft;

        $boxTop = $this->y;
        $this->y += $style->paddingTop;

        $streamBefore = $this->stream;
        $mark = $streamBefore->mark();
        $streamBefore->invalidateColorCache();

        $this->layoutParagraphRun($this->flattenInline($node), $innerX, $innerWidth, $style);

        $this->y += $style->paddingBottom;
        $boxHeight = $this->y - $boxTop;

        $this->spliceBoxDecorations($style, $x, $boxTop, $width, $boxHeight, $streamBefore, $mark);
    }

    /**
     * Draw a box's background/border by splicing ops in at a position recorded
     * before its content was laid out, so the background paints behind the
     * text instead of over it. If a page break happened while laying out the
     * content (stream identity changed), we skip decoration rather than draw
     * it in the wrong place - boxes are expected to be short enough to avoid this.
     */
    private function spliceBoxDecorations(Style $style, float $x, float $topY, float $width, float $height, ContentStream $streamBefore, int $mark): void
    {
        if ($style->backgroundColor === null && !$this->hasBorder($style)) {
            return;
        }
        if ($this->stream !== $streamBefore) {
            return; // content spanned a page break; skip rather than misplace
        }

        $ops = '';
        $pdfBottom = $this->pdfY($topY) - $height;

        if ($style->backgroundColor !== null) {
            [$r, $g, $b] = $style->backgroundColor;
            $ops .= sprintf(
                "%s %s %s rg\n%s %s %s %s re f\n",
                $this->writer->fmt(max(0, min(255, $r)) / 255),
                $this->writer->fmt(max(0, min(255, $g)) / 255),
                $this->writer->fmt(max(0, min(255, $b)) / 255),
                $this->writer->fmt($x),
                $this->writer->fmt($pdfBottom),
                $this->writer->fmt($width),
                $this->writer->fmt($height)
            );
        }

        if ($this->hasBorder($style)) {
            $ops .= $this->buildBorderOps($style, $x, $topY, $width, $height);
        }

        $streamBefore->insertAt($mark, $ops);
        $streamBefore->invalidateColorCache();
    }

    private function buildBorderOps(Style $style, float $x, float $topY, float $width, float $height): string
    {
        $bw = $style->borderWidth;
        [$r, $g, $b] = $style->borderColor;
        $top = $this->pdfY($topY);
        $bottom = $top - $height;
        $right = $x + $width;

        $ops = sprintf(
            "%s %s %s RG\n%s w\n",
            $this->writer->fmt(max(0, min(255, $r)) / 255),
            $this->writer->fmt(max(0, min(255, $g)) / 255),
            $this->writer->fmt(max(0, min(255, $b)) / 255),
            $this->writer->fmt($bw)
        );

        $dashOn = '';
        $dashOff = '';
        if ($style->borderStyle === 'dashed') {
            $dashOn = "[4 2] 0 d\n";
            $dashOff = "[] 0 d\n";
        } elseif ($style->borderStyle === 'dotted') {
            $dashOn = "[1 1.5] 0 d\n";
            $dashOff = "[] 0 d\n";
        }
        $ops .= $dashOn;

        if ($style->borderTop) {
            $ops .= sprintf("%s %s m %s %s l S\n", $this->writer->fmt($x), $this->writer->fmt($top), $this->writer->fmt($right), $this->writer->fmt($top));
        }
        if ($style->borderBottom) {
            $ops .= sprintf("%s %s m %s %s l S\n", $this->writer->fmt($x), $this->writer->fmt($bottom), $this->writer->fmt($right), $this->writer->fmt($bottom));
        }
        if ($style->borderLeft) {
            $ops .= sprintf("%s %s m %s %s l S\n", $this->writer->fmt($x), $this->writer->fmt($bottom), $this->writer->fmt($x), $this->writer->fmt($top));
        }
        if ($style->borderRight) {
            $ops .= sprintf("%s %s m %s %s l S\n", $this->writer->fmt($right), $this->writer->fmt($bottom), $this->writer->fmt($right), $this->writer->fmt($top));
        }
        $ops .= $dashOff;

        return $ops;
    }

    private function drawBoxDecorations(Style $style, float $x, float $topY, float $width, float $height): void
    {
        if ($style->backgroundColor === null && !$this->hasBorder($style)) {
            return;
        }
        $pdfBottom = $this->pdfY($topY) - $height;
        if ($style->backgroundColor !== null) {
            $this->stream->rect($x, $pdfBottom, $width, $height, $style->backgroundColor);
        }
        $this->drawBorders($style, $x, $topY, $width, $height);
    }

    private function hasBorder(Style $style): bool
    {
        return $style->borderTop || $style->borderRight || $style->borderBottom || $style->borderLeft;
    }

    private function drawBorders(Style $style, float $x, float $topY, float $width, float $height): void
    {
        $bw = $style->borderWidth;
        $color = $style->borderColor;
        $borderStyle = $style->borderStyle;
        $top = $this->pdfY($topY);
        $bottom = $top - $height;
        $right = $x + $width;

        if ($style->borderTop) {
            $this->stream->line($x, $top, $right, $top, $color, $bw, $borderStyle);
        }
        if ($style->borderBottom) {
            $this->stream->line($x, $bottom, $right, $bottom, $color, $bw, $borderStyle);
        }
        if ($style->borderLeft) {
            $this->stream->line($x, $bottom, $x, $top, $color, $bw, $borderStyle);
        }
        if ($style->borderRight) {
            $this->stream->line($right, $bottom, $right, $top, $color, $bw, $borderStyle);
        }
    }

    /**
     * Lay out a run of inline text nodes (possibly mixed styles) as wrapped
     * paragraph lines within $width, starting at $x.
     * @param Node[] $runs
     */
    private function layoutParagraphRun(array $runs, float $x, float $width, ?Style $blockStyle = null): void
    {
        $lines = $this->wrapRuns($runs, $width);
        if (empty($lines)) {
            return;
        }

        $lineHeight = ($blockStyle->fontSize ?? ($runs[0]->style->fontSize ?? 12)) * ($blockStyle->lineHeight ?? $runs[0]->style->lineHeight ?? 1.35);
        $align = $blockStyle->textAlign ?? ($runs[0]->style->textAlign ?? 'left');

        foreach ($lines as $line) {
            $this->ensureSpace($lineHeight);
            $lineWidth = array_sum(array_map(fn ($seg) => $seg['width'], $line));
            $startX = match ($align) {
                'center' => $x + max(0, ($width - $lineWidth) / 2),
                'right' => $x + max(0, $width - $lineWidth),
                default => $x,
            };

            $cursor = $startX;
            $baselineY = $this->pdfY($this->y + $lineHeight * 0.78);
            foreach ($line as $seg) {
                $fontKey = $this->writer->fontResourceKey($seg['font']);
                $this->stream->text($cursor, $baselineY, $seg['text'], $fontKey, $seg['size'], $seg['color']);
                $cursor += $seg['width'];
            }
            $this->y += $lineHeight;
        }
    }

    /**
     * Greedy word-wrap across possibly multiple styled runs.
     * @param Node[] $runs
     * @return array<int,array<int,array{text:string,width:float,font:string,size:float,color:array}>>
     */
    private function wrapRuns(array $runs, float $width): array
    {
        $lines = [];
        $currentLine = [];
        $currentLineWidth = 0.0;
        $spaceWidth = null;

        foreach ($runs as $run) {
            if ($run->tag === 'br') {
                $lines[] = $currentLine;
                $currentLine = [];
                $currentLineWidth = 0.0;
                continue;
            }

            $font = $run->style->pdfFontName();
            $size = $run->style->fontSize;
            $color = $run->style->color;
            $words = preg_split('/(\s+)/u', $run->text ?? '', -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $w = Metrics::stringWidth($font, $word, $size);
                $isSpace = trim($word) === '';

                if (!$isSpace && $currentLineWidth + $w > $width && $currentLineWidth > 0) {
                    // Trim a single trailing space segment before wrapping
                    if (!empty($currentLine) && trim(end($currentLine)['text']) === '') {
                        $trailing = array_pop($currentLine);
                        $currentLineWidth -= $trailing['width'];
                    }
                    $lines[] = $currentLine;
                    $currentLine = [];
                    $currentLineWidth = 0.0;
                }

                $currentLine[] = ['text' => $word, 'width' => $w, 'font' => $font, 'size' => $size, 'color' => $color];
                $currentLineWidth += $w;
            }
        }

        if (!empty($currentLine)) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    private function layoutHr(Node $node, float $x, float $width): void
    {
        $this->y += $node->style->marginTop;
        $this->ensureSpace(2);
        $py = $this->pdfY($this->y);
        $this->stream->line($x, $py, $x + $width, $py, [180, 180, 180], 1.0);
        $this->y += $node->style->marginBottom;
    }

    private function layoutList(Node $list, float $x, float $width): void
    {
        $ordered = $list->tag === 'ol';
        $i = 1;
        foreach ($list->children as $li) {
            if ($li->tag !== 'li') {
                continue;
            }
            $marker = $ordered ? ($i++ . '.') : '\u{2022}';
            $markerNode = new Node('#text', $li->style);
            $markerNode->text = $marker . ' ';
            $runs = array_merge([$markerNode], $this->flattenInline($li));
            $this->layoutParagraphRun($runs, $x + 12, $width - 12, $li->style);
        }
    }

    private function layoutTable(Node $table, float $x, float $width): void
    {
        $this->y += $table->style->marginTop;

        $rows = [];
        $headerRowCount = 0;
        foreach ($table->children as $section) {
            if ($section->tag === 'thead') {
                foreach ($section->children as $tr) {
                    if ($tr->tag === 'tr') {
                        $rows[] = $tr;
                        $headerRowCount++;
                    }
                }
            } elseif (in_array($section->tag, ['tbody', 'tfoot'], true)) {
                foreach ($section->children as $tr) {
                    if ($tr->tag === 'tr') {
                        $rows[] = $tr;
                    }
                }
            } elseif ($section->tag === 'tr') {
                $rows[] = $section;
            }
        }

        if (empty($rows)) {
            return;
        }

        $colCount = 0;
        foreach ($rows[0]->children as $cell) {
            $colCount += $cell->colspan;
        }
        $colCount = max(1, $colCount);

        // Column widths: honor explicit widths (points or %) on first-row cells, split the rest evenly.
        $colWidths = array_fill(0, $colCount, null);
        $ci = 0;
        foreach ($rows[0]->children as $cell) {
            if ($ci < $colCount) {
                if ($cell->style->widthPercent !== null && $cell->colspan === 1) {
                    $colWidths[$ci] = $width * ($cell->style->widthPercent / 100);
                } elseif ($cell->style->width !== null && $cell->colspan === 1) {
                    $colWidths[$ci] = $cell->style->width;
                }
            }
            $ci += $cell->colspan;
        }
        $definedTotal = array_sum(array_filter($colWidths, fn ($w) => $w !== null));
        $undefinedCount = count(array_filter($colWidths, fn ($w) => $w === null));
        $remaining = max(0, $width - $definedTotal);
        $autoWidth = $undefinedCount > 0 ? $remaining / $undefinedCount : 0;
        for ($i = 0; $i < $colCount; $i++) {
            if ($colWidths[$i] === null) {
                $colWidths[$i] = $autoWidth;
            }
        }

        foreach ($rows as $rowIndex => $tr) {
            $this->layoutTableRow($tr, $x, $colWidths);
            if ($rowIndex === $headerRowCount - 1) {
                // no-op placeholder: header repeat on page break is a possible future enhancement
            }
        }

        $this->y += $table->style->marginBottom;
    }

    private function layoutTableRow(Node $tr, float $x, array $colWidths): void
    {
        // Pre-measure each cell's wrapped lines to determine row height.
        $cellLines = [];
        $cellX = $x;
        $ci = 0;
        foreach ($tr->children as $cell) {
            if (!in_array($cell->tag, ['td', 'th'], true)) {
                continue;
            }
            $span = $cell->colspan;
            $cellWidth = array_sum(array_slice($colWidths, $ci, $span));
            $innerWidth = $cellWidth - $cell->style->paddingLeft - $cell->style->paddingRight;
            $runs = $this->flattenInline($cell);
            $lines = $this->wrapRuns($runs, max(1, $innerWidth));
            $lineHeight = $cell->style->fontSize * $cell->style->lineHeight;
            $cellHeight = max($lineHeight, count($lines) * $lineHeight) + $cell->style->paddingTop + $cell->style->paddingBottom;

            $cellLines[] = [
                'cell' => $cell, 'x' => $cellX, 'width' => $cellWidth,
                'lines' => $lines, 'lineHeight' => $lineHeight, 'height' => $cellHeight,
            ];
            $cellX += $cellWidth;
            $ci += $span;
        }

        $rowHeight = 0.0;
        foreach ($cellLines as $c) {
            $rowHeight = max($rowHeight, $c['height']);
        }

        $this->ensureSpace($rowHeight);
        $rowTop = $this->y;

        foreach ($cellLines as $c) {
            $cell = $c['cell'];
            $style = $cell->style;

            if ($style->backgroundColor !== null) {
                $pdfBottom = $this->pdfY($rowTop) - $rowHeight;
                $this->stream->rect($c['x'], $pdfBottom, $c['width'], $rowHeight, $style->backgroundColor);
            }
            $this->drawBorders($style, $c['x'], $rowTop, $c['width'], $rowHeight);

            $textX = $c['x'] + $style->paddingLeft;
            $textWidth = $c['width'] - $style->paddingLeft - $style->paddingRight;
            $ty = $rowTop + $style->paddingTop;

            foreach ($c['lines'] as $line) {
                $lineWidth = array_sum(array_map(fn ($seg) => $seg['width'], $line));
                $lineStartX = match ($style->textAlign) {
                    'center' => $textX + max(0, ($textWidth - $lineWidth) / 2),
                    'right' => $textX + max(0, $textWidth - $lineWidth),
                    default => $textX,
                };
                $cursor = $lineStartX;
                $baselineY = $this->pdfY($ty + $c['lineHeight'] * 0.78);
                foreach ($line as $seg) {
                    $fontKey = $this->writer->fontResourceKey($seg['font']);
                    $this->stream->text($cursor, $baselineY, $seg['text'], $fontKey, $seg['size'], $seg['color']);
                    $cursor += $seg['width'];
                }
                $ty += $c['lineHeight'];
            }
        }

        $this->y += $rowHeight;
    }
}
