<?php

namespace HtmlPdf\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class Parser
{
    /** @var array<int,array{selector:string,decls:array<string,string>}> */
    private array $stylesheet = [];

    public function parse(string $html): Node
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Force UTF-8 interpretation regardless of what the source declares.
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        foreach ($dom->getElementsByTagName('style') as $styleEl) {
            $this->stylesheet = array_merge($this->stylesheet, CssParser::parseStylesheet($styleEl->textContent));
        }

        $body = $dom->getElementsByTagName('body')->item(0) ?? $dom->documentElement;
        $root = new Node('body', new Style());

        $this->walkChildren($body, $root, new Style());

        return $root;
    }

    private function walkChildren(DOMNode $domNode, Node $parent, Style $inherited): void
    {
        foreach ($domNode->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = preg_replace('/\s+/u', ' ', $child->textContent);
                if (trim($text) === '' ) {
                    continue;
                }
                $textNode = new Node('#text', $inherited);
                $textNode->text = $text;
                $parent->children[] = $textNode;
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'head'], true)) {
                continue;
            }

            $style = $this->computeStyle($child, $tag, $inherited);
            $node = new Node($tag, $style, $this->attrs($child));

            if ($tag === 'td' || $tag === 'th') {
                $colspan = (int) ($child->getAttribute('colspan') ?: 1);
                $node->colspan = max(1, $colspan);
            }

            $parent->children[] = $node;
            $this->walkChildren($child, $node, $style);
        }
    }

    private function attrs(DOMElement $el): array
    {
        $out = [];
        foreach ($el->attributes as $attr) {
            $out[$attr->name] = $attr->value;
        }
        return $out;
    }

    private function computeStyle(DOMElement $el, string $tag, Style $inherited): Style
    {
        $style = $inherited->clone();

        // 1) Tag defaults
        $this->applyTagDefaults($tag, $style);

        // 2) Stylesheet rules (in source order, ordered by specificity)
        $classAttr = $el->getAttribute('class');
        $idAttr = $el->getAttribute('id');
        $matched = [];
        foreach ($this->stylesheet as $rule) {
            if (CssParser::matches($rule['selector'], $tag, $classAttr, $idAttr)) {
                $matched[] = $rule;
            }
        }
        usort($matched, fn ($a, $b) => CssParser::specificity($a['selector']) <=> CssParser::specificity($b['selector']));
        foreach ($matched as $rule) {
            $this->applyDeclarations($rule['decls'], $style);
        }

        // 3) Inline style (highest priority)
        $inline = $el->getAttribute('style');
        if ($inline !== '') {
            $this->applyDeclarations(CssParser::parseDeclarations($inline), $style);
        }

        return $style;
    }

    private function applyTagDefaults(string $tag, Style $style): void
    {
        switch ($tag) {
            case 'h1': $style->fontSize = 22; $style->bold = true; $style->marginTop = 14; $style->marginBottom = 10; break;
            case 'h2': $style->fontSize = 18; $style->bold = true; $style->marginTop = 12; $style->marginBottom = 8; break;
            case 'h3': $style->fontSize = 15; $style->bold = true; $style->marginTop = 10; $style->marginBottom = 6; break;
            case 'h4': case 'h5': case 'h6':
                $style->fontSize = 13; $style->bold = true; $style->marginTop = 8; $style->marginBottom = 5; break;
            case 'p': $style->marginTop = 0; $style->marginBottom = 8; break;
            case 'strong': case 'b': $style->bold = true; break;
            case 'em': case 'i': $style->italic = true; break;
            case 'table': $style->marginTop = 4; $style->marginBottom = 4; break;
            case 'th':
                $style->bold = true; $style->textAlign = 'center';
                $style->paddingTop = 4; $style->paddingRight = 4; $style->paddingBottom = 4; $style->paddingLeft = 4;
                $style->borderTop = $style->borderRight = $style->borderBottom = $style->borderLeft = true;
                break;
            case 'td':
                $style->paddingTop = 4; $style->paddingRight = 4; $style->paddingBottom = 4; $style->paddingLeft = 4;
                $style->borderTop = $style->borderRight = $style->borderBottom = $style->borderLeft = true;
                break;
            case 'li': $style->marginBottom = 3; break;
            case 'hr': $style->marginTop = 8; $style->marginBottom = 8; break;
        }
    }

    private function applyDeclarations(array $decls, Style $style): void
    {
        foreach ($decls as $prop => $val) {
            switch ($prop) {
                case 'font-size':
                    $style->fontSize = $this->toPt($val, $style->fontSize);
                    break;
                case 'font-weight':
                    $style->bold = in_array(strtolower($val), ['bold', 'bolder', '700', '800', '900'], true);
                    break;
                case 'font-style':
                    $style->italic = strtolower($val) === 'italic';
                    break;
                case 'font-family':
                    $fam = strtolower($val);
                    $style->fontFamily = str_contains($fam, 'times') || str_contains($fam, 'serif') ? 'Times'
                        : (str_contains($fam, 'courier') || str_contains($fam, 'mono') ? 'Courier' : 'Helvetica');
                    break;
                case 'color':
                    $style->color = $this->toRgb($val) ?? $style->color;
                    break;
                case 'background-color':
                    $style->backgroundColor = $this->toRgb($val);
                    break;
                case 'text-align':
                    $style->textAlign = strtolower($val);
                    break;
                case 'line-height':
                    $style->lineHeight = is_numeric($val) ? (float) $val : $style->lineHeight;
                    break;
                case 'width':
                    if (preg_match('/^(-?[\d.]+)\s*%$/', trim($val), $m)) {
                        $style->widthPercent = (float) $m[1];
                        $style->width = null;
                    } else {
                        $style->width = $this->toPt($val, 0) ?: null;
                        $style->widthPercent = null;
                    }
                    break;
                case 'margin':
                    [$style->marginTop, $style->marginRight, $style->marginBottom, $style->marginLeft] = $this->box($val, $style->fontSize);
                    break;
                case 'margin-top': $style->marginTop = $this->toPt($val, $style->marginTop); break;
                case 'margin-right': $style->marginRight = $this->toPt($val, $style->marginRight); break;
                case 'margin-bottom': $style->marginBottom = $this->toPt($val, $style->marginBottom); break;
                case 'margin-left': $style->marginLeft = $this->toPt($val, $style->marginLeft); break;
                case 'padding':
                    [$style->paddingTop, $style->paddingRight, $style->paddingBottom, $style->paddingLeft] = $this->box($val, $style->fontSize);
                    break;
                case 'padding-top': $style->paddingTop = $this->toPt($val, $style->paddingTop); break;
                case 'padding-right': $style->paddingRight = $this->toPt($val, $style->paddingRight); break;
                case 'padding-bottom': $style->paddingBottom = $this->toPt($val, $style->paddingBottom); break;
                case 'padding-left': $style->paddingLeft = $this->toPt($val, $style->paddingLeft); break;
                case 'border':
                    if (strtolower(trim($val)) === 'none' || trim($val) === '0') {
                        $style->borderTop = $style->borderRight = $style->borderBottom = $style->borderLeft = false;
                    } else {
                        $this->applyBorderShorthand($val, $style);
                        $style->borderTop = $style->borderRight = $style->borderBottom = $style->borderLeft = true;
                    }
                    break;
                case 'border-top':
                    if (strtolower(trim($val)) === 'none' || trim($val) === '0') {
                        $style->borderTop = false;
                    } else {
                        $this->applyBorderShorthand($val, $style);
                        $style->borderTop = true;
                    }
                    break;
                case 'border-right':
                    if (strtolower(trim($val)) === 'none' || trim($val) === '0') {
                        $style->borderRight = false;
                    } else {
                        $this->applyBorderShorthand($val, $style);
                        $style->borderRight = true;
                    }
                    break;
                case 'border-bottom':
                    if (strtolower(trim($val)) === 'none' || trim($val) === '0') {
                        $style->borderBottom = false;
                    } else {
                        $this->applyBorderShorthand($val, $style);
                        $style->borderBottom = true;
                    }
                    break;
                case 'border-left':
                    if (strtolower(trim($val)) === 'none' || trim($val) === '0') {
                        $style->borderLeft = false;
                    } else {
                        $this->applyBorderShorthand($val, $style);
                        $style->borderLeft = true;
                    }
                    break;
                case 'border-width':
                    $style->borderWidth = $this->toPt($val, $style->borderWidth);
                    break;
                case 'border-color':
                    $style->borderColor = $this->toRgb($val) ?? $style->borderColor;
                    break;
            }
        }
    }

    private function applyBorderShorthand(string $val, Style $style): void
    {
        // e.g. "1px solid #000000"
        if (preg_match('/([\d.]+)(px|pt)?/', $val, $m)) {
            $style->borderWidth = $this->toPt($m[1] . ($m[2] ?? 'px'), 1);
        }
        if (preg_match('/#[0-9a-fA-F]{3,6}|rgb\([^)]+\)/', $val, $m)) {
            $style->borderColor = $this->toRgb($m[0]) ?? $style->borderColor;
        }
    }

    /** @return float[] [top,right,bottom,left] */
    private function box(string $val, float $base): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($val))));
        $nums = array_map(fn ($p) => $this->toPt($p, 0), $parts);
        return match (count($nums)) {
            1 => [$nums[0], $nums[0], $nums[0], $nums[0]],
            2 => [$nums[0], $nums[1], $nums[0], $nums[1]],
            3 => [$nums[0], $nums[1], $nums[2], $nums[1]],
            4 => [$nums[0], $nums[1], $nums[2], $nums[3]],
            default => [$base, $base, $base, $base],
        };
    }

    private function toPt(string $val, float $default): float
    {
        $val = trim($val);
        if (preg_match('/^(-?[\d.]+)\s*(px|pt|em|%)?$/', $val, $m)) {
            $n = (float) $m[1];
            return match ($m[2] ?? 'pt') {
                'px' => $n * 0.75,
                'em' => $n * 12,
                '%' => $n, // caller interprets relative to context
                default => $n,
            };
        }
        return $default;
    }

    private function toRgb(string $val): ?array
    {
        $val = trim($val);
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $val, $m)) {
            return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
        }
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $val, $m)) {
            [$r, $g, $b] = str_split($m[1]);
            return [hexdec("$r$r"), hexdec("$g$g"), hexdec("$b$b")];
        }
        if (preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $val, $m)) {
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }
        $named = [
            'black' => [0, 0, 0], 'white' => [255, 255, 255], 'red' => [255, 0, 0],
            'green' => [0, 128, 0], 'blue' => [0, 0, 255], 'gray' => [128, 128, 128],
            'grey' => [128, 128, 128], 'silver' => [192, 192, 192], 'darkgray' => [169, 169, 169],
        ];
        return $named[strtolower($val)] ?? null;
    }
}
