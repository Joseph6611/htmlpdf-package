<?php

namespace HtmlPdf\Html;

class Style
{
    public float $fontSize = 12.0;
    public bool $bold = false;
    public bool $italic = false;
    public string $fontFamily = 'Helvetica';
    public array $color = [0, 0, 0];
    public ?array $backgroundColor = null;
    public string $textAlign = 'left'; // left, center, right, justify
    public float $lineHeight = 1.35;

    public float $marginTop = 0;
    public float $marginRight = 0;
    public float $marginBottom = 0;
    public float $marginLeft = 0;

    public float $paddingTop = 0;
    public float $paddingRight = 0;
    public float $paddingBottom = 0;
    public float $paddingLeft = 0;

    public bool $borderTop = false;
    public bool $borderRight = false;
    public bool $borderBottom = false;
    public bool $borderLeft = false;
    public array $borderColor = [0, 0, 0];
    public float $borderWidth = 1.0;
    public string $borderStyle = 'solid'; // solid, dashed, dotted

    public ?float $width = null; // points, or null = auto
    public ?float $widthPercent = null; // 0-100, resolved against container width at layout time
    public ?float $height = null; // points, or null = auto (currently only used by <img>)

    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Reset the properties CSS does not inherit (box model, background, size).
     * Used for replaced elements such as <img>, which must not pick up the
     * width/padding/border of the block that contains them.
     */
    public function resetBox(): void
    {
        $this->width = null;
        $this->widthPercent = null;
        $this->height = null;
        $this->backgroundColor = null;
        $this->marginTop = $this->marginRight = $this->marginBottom = $this->marginLeft = 0;
        $this->paddingTop = $this->paddingRight = $this->paddingBottom = $this->paddingLeft = 0;
        $this->borderTop = $this->borderRight = $this->borderBottom = $this->borderLeft = false;
    }

    public function pdfFontName(): string
    {
        $family = $this->fontFamily;
        if ($family === 'Times') {
            return $this->bold && $this->italic ? 'Times-BoldItalic'
                : ($this->bold ? 'Times-Bold' : ($this->italic ? 'Times-Italic' : 'Times-Roman'));
        }
        if ($family === 'Courier') {
            return $this->bold && $this->italic ? 'Courier-BoldOblique'
                : ($this->bold ? 'Courier-Bold' : ($this->italic ? 'Courier-Oblique' : 'Courier'));
        }
        // Default: Helvetica
        return $this->bold && $this->italic ? 'Helvetica-BoldOblique'
            : ($this->bold ? 'Helvetica-Bold' : ($this->italic ? 'Helvetica-Oblique' : 'Helvetica'));
    }
}
