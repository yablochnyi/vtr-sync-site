<?php

namespace App\Services;

use App\Models\AiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiClient
{
    public function chat(array $messages, array $options = []): array
    {
        $settings = AiSettings::query()->first()?->settings ?? [];

        $baseUrl = trim((string) ($settings['url'] ?? ''));
        $apiKey = trim((string) ($settings['key'] ?? ''));
        $model = (string) ($options['model'] ?? ($settings['model'] ?? ''));

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('AI settings are not configured (url/key).');
        }

        if ($model === '') {
            throw new \RuntimeException('AI model is not configured.');
        }

        $endpoint = $this->chatCompletionsEndpoint($baseUrl);

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        $temperature = $options['temperature'] ?? null;
        if ($temperature !== null && $temperature !== '') {
            $payload['temperature'] = (float) $temperature;
        }

        $maxTokens = $options['max_tokens'] ?? $settings['max_tokens'] ?? null;
        if ($maxTokens !== null && $maxTokens !== '') {
            $payload['max_tokens'] = (int) $maxTokens;
        }
        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new \RuntimeException("AI request failed: {$response->status()} {$response->body()}");
        }

        return (array) $response->json();
    }

    public function generateImagePng(string $prompt, array $options = []): string
    {
        $settings = AiSettings::query()->first()?->settings ?? [];

        $baseUrl = trim((string) ($settings['url'] ?? ''));
        $apiKey = trim((string) ($settings['key'] ?? ''));

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('AI settings are not configured (url/key).');
        }

        $endpoint = $this->imagesGenerationsEndpoint($baseUrl);

        $payload = [
            'model' => (string) ($options['model'] ?? 'gpt-image-1'),
            'prompt' => $prompt,
            'size' => (string) ($options['size'] ?? '1024x1024'),
        ];

        $timeout = (int) ($options['timeout'] ?? 90);
        $timeout = max(10, min(300, $timeout));

        $http = Http::timeout($timeout)->withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ]);

        // Many OpenAI-compatible providers do not support `response_format`,
        // so we avoid it and accept either a returned `url` or `b64_json`.
        $response = $http->post($endpoint, $payload);

        if ($response->failed()) {
            throw new \RuntimeException("AI image request failed: {$response->status()} {$response->body()}");
        }

        $json = (array) $response->json();
        $item = $json['data'][0] ?? null;
        $item = is_array($item) ? $item : [];

        $b64 = $item['b64_json'] ?? null;
        if (is_string($b64) && $b64 !== '') {
            $bin = base64_decode($b64, true);
            if (!is_string($bin) || $bin === '') {
                throw new \RuntimeException('AI image b64_json could not be decoded.');
            }

            return $bin;
        }

        // Fallback: some providers return a URL.
        $url = $item['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $imgResp = Http::timeout($timeout)->get($url);
            if ($imgResp->failed()) {
                throw new \RuntimeException("AI image url download failed: {$imgResp->status()} {$imgResp->body()}");
            }

            return (string) $imgResp->body();
        }

        throw new \RuntimeException('AI image response did not include b64_json or url.');
    }

    public function extractFirstMessageContent(array $response): string
    {
        $choices = $response['choices'] ?? null;
        if (!is_array($choices) || $choices === []) {
            return '';
        }

        $content = $choices[0]['message']['content'] ?? '';
        return is_string($content) ? $content : '';
    }

    private function chatCompletionsEndpoint(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/');

        if (Str::contains($url, '/chat/completions')) {
            return $url;
        }

        if (preg_match('#/v1$#', $url)) {
            return $url . '/chat/completions';
        }

        return $url . '/v1/chat/completions';
    }

    private function imagesGenerationsEndpoint(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/');

        if (Str::contains($url, '/chat/completions')) {
            $url = preg_replace('#/chat/completions$#', '', $url) ?: $url;
        }

        if (!preg_match('#/v1$#', $url)) {
            $url = $url . '/v1';
        }

        return $url . '/images/generations';
    }
}

