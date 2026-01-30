<?php

namespace App\Services\Generation;

use Illuminate\Support\Str;

class PromptRenderer
{
    public function render(string $template, array $vars): string
    {
        $out = $template;

        foreach ($vars as $k => $v) {
            $key = preg_quote((string) $k, '/');
            $value = (string) $v;

            // Support both {{topic}} and {{ topic }} forms.
            $out = preg_replace('/\{\{\s*' . $key . '\s*\}\}/u', $value, $out) ?? $out;
        }

        return Str::of($out)->toString();
    }
}

