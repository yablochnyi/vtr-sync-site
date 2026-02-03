<?php

namespace App\Services\Generation;

use Illuminate\Support\Str;

class LinkInserter
{

    public function insert(string $html, array $links): string
    {
        $links = array_values(array_filter($links, fn ($l) => !empty($l['url']) && !empty($l['anchor'])));
        if ($links === [] || trim($html) === '') {
            return $html;
        }

        $links = array_values(array_filter($links, function ($l) use ($html) {
            $url = (string) ($l['url'] ?? '');
            if ($url === '') {
                return false;
            }

            return !Str::contains($html, 'href="' . $url . '"');
        }));
        if ($links === []) {
            return $html;
        }

        $hasParagraphs = Str::contains($html, '</p>');
        if (!$hasParagraphs) {
            $parts = preg_split("/\n\s*\n/", trim($html)) ?: [];
            $html = implode("\n\n", array_map(fn ($p) => '<p>' . e(trim($p)) . '</p>', $parts));
        }

        preg_match_all('#<p\b[^>]*>.*?</p>#is', $html, $m);
        $paragraphs = array_values(array_filter(array_map('trim', $m[0] ?? []), fn ($p) => $p !== ''));

        if ($paragraphs === []) {
            $parts = preg_split('#</p>#i', $html) ?: [];
            $paragraphs = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
            $paragraphs = array_map(fn ($p) => Str::endsWith(Str::lower($p), '</p>') ? $p : ($p . '</p>'), $paragraphs);
        }

        $count = count($paragraphs);
        if ($count === 0) {
            return $html;
        }

        $k = count($links);
        $insertPositions = [];
        for ($i = 1; $i <= $k; $i++) {
            $pos = (int) floor(($i * $count) / ($k + 1));
            $insertPositions[] = max(0, min($count, $pos));
        }

        $used = [];
        $linkIdx = 0;

        for ($j = 0; $j < $k; $j++) {
            $pos = $insertPositions[$j] ?? 0;
            $pos = max(0, min($count - 1, $pos));

            $idx = $pos;
            for ($step = 0; $step < $count; $step++) {
                $try = ($pos + $step) % $count;
                if (!isset($used[$try])) {
                    $idx = $try;
                    break;
                }
            }

            $used[$idx] = true;
            $paragraphs[$idx] = $this->injectLinkIntoParagraph($paragraphs[$idx], $links[$linkIdx]);
            $linkIdx++;
        }

        return implode("\n", array_map(
            fn ($p) => Str::endsWith(Str::lower($p), '</p>') ? $p : ($p . '</p>'),
            $paragraphs
        ));
    }

    private function injectLinkIntoParagraph(string $paragraphHtml, array $link): string
    {
        $url = e($link['url']);
        $anchor = e($link['anchor']);
        $newTab = (bool) ($link['new_tab'] ?? false);

        $attrs = $newTab
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        $snippet = " Читайте также: <a href=\"{$url}\"{$attrs}>{$anchor}</a>.";

        if (preg_match('#</p>\s*$#i', $paragraphHtml)) {
            return preg_replace('#</p>\s*$#i', $snippet . '</p>', $paragraphHtml) ?? ($paragraphHtml . $snippet);
        }

        return $paragraphHtml . $snippet;
    }
}

