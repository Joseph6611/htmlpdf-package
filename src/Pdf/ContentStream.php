<?php

namespace HtmlPdf\Pdf;

/**
 * Accumulates PDF content-stream operators for a single page.
 * Coordinate origin is bottom-left (PDF native); callers using a
 * top-left layout system should flip Y before calling these methods.
 */
class ContentStream
{
    private string $buf = '';
    private ?string $currentFontKey = null;
    private float $currentFontSize = 0;
    private ?array $currentFillColor = null;
    private ?array $currentStrokeColor = null;
    private ?float $currentLineWidth = null;

    public function __construct(private Writer $writer)
    {
    }

    public function text(float $x, float $y, string $text, string $fontKey, float $size, ?array $rgb = null): void
    {
        if ($text === '') {
            return;
        }
        $this->setFillColor($rgb ?? [0, 0, 0]);
        $this->buf .= "BT\n";
        if ($this->currentFontKey !== $fontKey || $this->currentFontSize !== $size) {
            $this->buf .= "/{$fontKey} {$this->writer->fmt($size)} Tf\n";
        }
        $this->buf .= "{$this->writer->fmt($x)} {$this->writer->fmt($y)} Td\n";
        $this->buf .= '(' . $this->writer->escape($text) . ") Tj\n";
        $this->buf .= "ET\n";
    }

    public function rect(float $x, float $y, float $w, float $h, ?array $fillRgb = null, ?array $strokeRgb = null, float $lineWidth = 1.0): void
    {
        if ($fillRgb !== null) {
            $this->setFillColor($fillRgb);
        }
        if ($strokeRgb !== null) {
            $this->setStrokeColor($strokeRgb);
            $this->setLineWidth($lineWidth);
        }

        $op = match (true) {
            $fillRgb !== null && $strokeRgb !== null => 'B',
            $fillRgb !== null => 'f',
            $strokeRgb !== null => 'S',
            default => 'n',
        };

        $this->buf .= sprintf(
            "%s %s %s %s re %s\n",
            $this->writer->fmt($x),
            $this->writer->fmt($y),
            $this->writer->fmt($w),
            $this->writer->fmt($h),
            $op
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0, 0, 0], float $lineWidth = 1.0, string $style = 'solid'): void
    {
        $this->setStrokeColor($rgb);
        $this->setLineWidth($lineWidth);
        if ($style === 'dashed') {
            $this->buf .= "[4 2] 0 d\n";
        } elseif ($style === 'dotted') {
            $this->buf .= "[1 1.5] 0 d\n";
        }
        $this->buf .= sprintf(
            "%s %s m %s %s l S\n",
            $this->writer->fmt($x1),
            $this->writer->fmt($y1),
            $this->writer->fmt($x2),
            $this->writer->fmt($y2)
        );
        if ($style !== 'solid') {
            $this->buf .= "[] 0 d\n";
        }
    }

    /**
     * Paint an image XObject into the box whose bottom-left corner is ($x, $y)
     * (PDF coordinates, y pointing up). The q/Q pair keeps the transform from
     * leaking into the colour/line-width caches below.
     */
    public function image(string $key, float $x, float $y, float $w, float $h): void
    {
        $this->buf .= sprintf(
            "q %s 0 0 %s %s %s cm /%s Do Q\n",
            $this->writer->fmt($w),
            $this->writer->fmt($h),
            $this->writer->fmt($x),
            $this->writer->fmt($y),
            $key
        );
    }

    private function setFillColor(array $rgb): void
    {
        if ($this->currentFillColor === $rgb) {
            return;
        }
        $this->currentFillColor = $rgb;
        [$r, $g, $b] = $rgb;
        $this->buf .= sprintf("%s %s %s rg\n", $this->c($r), $this->c($g), $this->c($b));
    }

    private function setStrokeColor(array $rgb): void
    {
        if ($this->currentStrokeColor === $rgb) {
            return;
        }
        $this->currentStrokeColor = $rgb;
        [$r, $g, $b] = $rgb;
        $this->buf .= sprintf("%s %s %s RG\n", $this->c($r), $this->c($g), $this->c($b));
    }

    private function setLineWidth(float $w): void
    {
        if ($this->currentLineWidth === $w) {
            return;
        }
        $this->currentLineWidth = $w;
        $this->buf .= $this->writer->fmt($w) . " w\n";
    }

    private function c(int $v): string
    {
        return $this->writer->fmt(max(0, min(255, $v)) / 255);
    }

    public function raw(): string
    {
        return $this->buf;
    }

    /** Current write position, used to splice content in before what's written after this point. */
    public function mark(): int
    {
        return strlen($this->buf);
    }

    /** Insert raw PDF ops at a previously recorded position (used to draw box backgrounds behind their content). */
    public function insertAt(int $pos, string $code): void
    {
        $this->buf = substr($this->buf, 0, $pos) . $code . substr($this->buf, $pos);
    }

    /** Call after insertAt() with color-changing ops, since the splice can leave the state cache stale. */
    public function invalidateColorCache(): void
    {
        $this->currentFillColor = null;
        $this->currentStrokeColor = null;
        $this->currentLineWidth = null;
    }
}
