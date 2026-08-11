<?php

namespace HtmlPdf\Html;

class Node
{
    /** @var Node[] */
    public array $children = [];
    public ?string $text = null; // set for text nodes only
    public int $colspan = 1;

    public function __construct(
        public string $tag,
        public Style $style,
        public array $attributes = []
    ) {
    }

    public function isText(): bool
    {
        return $this->text !== null;
    }

    public function isBlock(): bool
    {
        return in_array($this->tag, [
            'div', 'p', 'body', 'html', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'table', 'ul', 'ol', 'li', 'hr', 'tr', 'thead', 'tbody',
        ], true);
    }
}
