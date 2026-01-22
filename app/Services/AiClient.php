<?php

namespace App\Services;

use App\Models\AiSettings;
use Illuminate\Support\Facades\Http;
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
}

