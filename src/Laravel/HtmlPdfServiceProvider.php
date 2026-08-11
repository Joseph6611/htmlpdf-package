<?php

namespace HtmlPdf\Laravel;

use HtmlPdf\HtmlPdf;
use Illuminate\Support\ServiceProvider;

class HtmlPdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('htmlpdf', fn () => HtmlPdf::create());
    }
}
