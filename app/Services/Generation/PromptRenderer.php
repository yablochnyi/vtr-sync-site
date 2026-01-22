<?php

namespace App\Services\Generation;

use Illuminate\Support\Str;

class PromptRenderer
{
    public function render(string $template, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = (string) $v;
        }

        return Str::of($template)->replace(array_keys($map), array_values($map))->toString();
    }
}

