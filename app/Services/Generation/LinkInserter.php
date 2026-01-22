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

        $hasParagraphs = Str::contains($html, '</p>');
        if (!$hasParagraphs) {
            $parts = preg_split("/\n\s*\n/", trim($html)) ?: [];
            $html = implode("\n\n", array_map(fn ($p) => '<p>' . e(trim($p)) . '</p>', $parts));
        }

        $paragraphs = preg_split('#</p>#i', $html);
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), fn ($p) => $p !== ''));

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

        $out = [];
        $linkIdx = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($linkIdx < $k && $insertPositions[$linkIdx] === $i) {
                $out[] = $this->renderLinkParagraph($links[$linkIdx]);
                $linkIdx++;
            }

            $p = $paragraphs[$i];
            $out[] = Str::endsWith(Str::lower($p), '</p>') ? $p : ($p . '</p>');
        }

        while ($linkIdx < $k) {
            $out[] = $this->renderLinkParagraph($links[$linkIdx]);
            $linkIdx++;
        }

        return implode("\n", $out);
    }

    private function renderLinkParagraph(array $link): string
    {
        $url = e($link['url']);
        $anchor = e($link['anchor']);
        $newTab = (bool) ($link['new_tab'] ?? false);

        $attrs = $newTab
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        return "<p><a href=\"{$url}\"{$attrs}>{$anchor}</a></p>";
    }
}

