<?php

namespace HtmlPdf\Html;

/**
 * Deliberately minimal CSS support: no cascading combinators, no
 * pseudo-classes, no media queries. Handles the subset needed for
 * document-style templates: tag / .class / #id / tag.class selectors,
 * comma-separated groups, applied in source order (later wins on tie).
 */
class CssParser
{
    /** @return array<int,array{selector:string,decls:array<string,string>}> */
    public static function parseStylesheet(string $css): array
    {
        $rules = [];
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER)) {
            return $rules;
        }

        foreach ($matches as $m) {
            $selectors = array_map('trim', explode(',', trim($m[1])));
            $decls = self::parseDeclarations($m[2]);
            foreach ($selectors as $sel) {
                if ($sel === '') {
                    continue;
                }
                $rules[] = ['selector' => $sel, 'decls' => $decls];
            }
        }

        return $rules;
    }

    /** @return array<string,string> */
    public static function parseDeclarations(string $block): array
    {
        $decls = [];
        foreach (explode(';', $block) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, ':')) {
                continue;
            }
            [$prop, $val] = explode(':', $pair, 2);
            $decls[strtolower(trim($prop))] = trim($val);
        }

        return $decls;
    }

    /**
     * Matches a single simple selector (tag, .class, #id, tag.class, or *)
     * against an element's tag/class/id.
     */
    public static function matches(string $selector, string $tag, string $classAttr, string $idAttr): bool
    {
        $selector = trim($selector);
        if ($selector === '*') {
            return true;
        }

        $classes = preg_split('/\s+/', trim($classAttr)) ?: [];

        // tag, .class, #id, or tag.class combos concatenated with no space
        if (preg_match_all('/(#|\.)?([a-zA-Z0-9_-]+)/', $selector, $parts, PREG_SET_ORDER) === 0) {
            return false;
        }

        foreach ($parts as $p) {
            $prefix = $p[1];
            $name = $p[2];
            if ($prefix === '#') {
                if ($idAttr !== $name) {
                    return false;
                }
            } elseif ($prefix === '.') {
                if (!in_array($name, $classes, true)) {
                    return false;
                }
            } else {
                if (strcasecmp($tag, $name) !== 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Rough specificity: id > class > tag, used to order same-target rules. */
    public static function specificity(string $selector): int
    {
        $ids = substr_count($selector, '#');
        $classes = substr_count($selector, '.');
        $tags = preg_match_all('/(^|[.#\s])[a-zA-Z][a-zA-Z0-9-]*/', $selector) - $classes - $ids;

        return $ids * 100 + $classes * 10 + max(0, $tags);
    }
}
