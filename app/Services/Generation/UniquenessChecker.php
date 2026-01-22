<?php

namespace App\Services\Generation;

class UniquenessChecker
{
    public function uniquenessPercent(string $html, array $existingHtmlList, int $shingleSize = 5): int
    {
        $candidate = $this->shingles($html, $shingleSize);
        if ($candidate === []) {
            return 0;
        }

        $maxSimilarity = 0.0;

        foreach ($existingHtmlList as $existing) {
            $other = $this->shingles((string) $existing, $shingleSize);
            if ($other === []) {
                continue;
            }

            $sim = $this->jaccard($candidate, $other);
            if ($sim > $maxSimilarity) {
                $maxSimilarity = $sim;
            }
        }

        $uniq = (1.0 - $maxSimilarity) * 100.0;

        return (int) max(0, min(100, round($uniq)));
    }

    /**
     * @return array<string,true>
     */
    private function shingles(string $html, int $size): array
    {
        $text = strip_tags($html);
        $text = mb_strtolower($text);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));

        if (count($tokens) < $size) {
            return [];
        }

        $out = [];
        for ($i = 0; $i <= count($tokens) - $size; $i++) {
            $shingle = implode(' ', array_slice($tokens, $i, $size));
            $out[$shingle] = true;
        }

        return $out;
    }

    /**
     * @param array<string,true> $a
     * @param array<string,true> $b
     */
    private function jaccard(array $a, array $b): float
    {
        $intersection = 0;

        foreach ($a as $k => $_) {
            if (isset($b[$k])) {
                $intersection++;
            }
        }

        $union = count($a) + count($b) - $intersection;

        return $union === 0 ? 0.0 : ($intersection / $union);
    }
}

