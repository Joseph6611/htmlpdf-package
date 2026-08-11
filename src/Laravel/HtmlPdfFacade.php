<?php

namespace HtmlPdf\Laravel;

use Illuminate\Support\Facades\Facade;
use HtmlPdf\HtmlPdf;

/**
 * @method static HtmlPdf loadHtml(string $html)
 * @method static HtmlPdf setPaper(string $size, string $orientation = 'portrait')
 * @method static HtmlPdf setMargins(float $top, float $right, float $bottom, float $left)
 */
class HtmlPdfFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'htmlpdf';
    }

    /** Convenience helper mirroring dompdf's Pdf::loadView(). */
    public static function loadView(string $view, array $data = []): HtmlPdf
    {
        $html = view($view, $data)->render();
        return HtmlPdf::create()->loadHtml($html);
    }
}
