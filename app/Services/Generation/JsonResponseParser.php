<?php

namespace App\Services\Generation;

class JsonResponseParser
{
    public function parseObject(string $content): array
    {
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $json = substr($content, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function parseList(string $content): array
    {
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '[');
        $end = strrpos($content, ']');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $json = substr($content, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}

