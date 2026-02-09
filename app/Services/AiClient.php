<?php

namespace App\Services;

use App\Models\AiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiClient
{
    private const LOG_CHAT_PATH = 'logs/ai-prompts.log';
    private const LOG_IMAGES_PATH = 'logs/ai-images.log';

    public function chat(array $messages, array $options = []): array
    {
        $settings = AiSettings::query()->first()?->settings ?? [];

        $baseUrl = trim((string) ($settings['url'] ?? ''));
        $apiKey = trim((string) ($settings['key'] ?? ''));
        $model = (string) ($options['model'] ?? ($settings['model'] ?? ''));
        $requestId = (string) Str::uuid();

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

        $this->logJsonLine(self::LOG_CHAT_PATH, 'ai.chat.request', [
            'ts' => now()->toAtomString(),
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'model' => $model,
            'max_tokens' => $payload['max_tokens'] ?? null,
            'temperature' => $payload['temperature'] ?? null,
            'context' => $options['context'] ?? null,
            'messages' => $this->truncateDeep($messages, 50000),
        ]);

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $this->logJsonLine(self::LOG_CHAT_PATH, 'ai.chat.error', [
                'ts' => now()->toAtomString(),
                'request_id' => $requestId,
                'status' => $response->status(),
                'body' => $this->truncateString($response->body(), 50000),
            ]);

            throw new \RuntimeException("AI request failed (request_id={$requestId}): {$response->status()} {$response->body()}");
        }

        $json = (array) $response->json();
        $first = $this->extractFirstMessageContent($json);

        $this->logJsonLine(self::LOG_CHAT_PATH, 'ai.chat.response', [
            'ts' => now()->toAtomString(),
            'request_id' => $requestId,
            'status' => $response->status(),
            'response_first_message' => $this->truncateString($first, 50000),
            'usage' => $json['usage'] ?? null,
        ]);

        return $json;
    }

    public function generateImagePng(string $prompt, array $options = []): string
    {
        $settings = AiSettings::query()->first()?->settings ?? [];

        $baseUrl = trim((string) ($settings['url'] ?? ''));
        $apiKey = trim((string) ($settings['key'] ?? ''));
        $requestId = (string) Str::uuid();

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

        $this->logJsonLine(self::LOG_IMAGES_PATH, 'ai.image.request', [
            'ts' => now()->toAtomString(),
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'payload' => $this->truncateDeep($payload, 50000),
            'context' => $options['context'] ?? null,
        ]);

        $response = $http->post($endpoint, $payload);

        if ($response->failed()) {
            $this->logJsonLine(self::LOG_IMAGES_PATH, 'ai.image.error', [
                'ts' => now()->toAtomString(),
                'request_id' => $requestId,
                'status' => $response->status(),
                'body' => $this->truncateString($response->body(), 50000),
            ]);

            throw new \RuntimeException("AI image request failed (request_id={$requestId}): {$response->status()} {$response->body()}");
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

            $this->logJsonLine(self::LOG_IMAGES_PATH, 'ai.image.response', [
                'ts' => now()->toAtomString(),
                'request_id' => $requestId,
                'mode' => 'b64_json',
                'bytes' => strlen($bin),
            ]);

            return $bin;
        }

        // Fallback: some providers return a URL.
        $url = $item['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $imgResp = Http::timeout($timeout)->get($url);
            if ($imgResp->failed()) {
                throw new \RuntimeException("AI image url download failed: {$imgResp->status()} {$imgResp->body()}");
            }

            $bin = (string) $imgResp->body();

            $this->logJsonLine(self::LOG_IMAGES_PATH, 'ai.image.response', [
                'ts' => now()->toAtomString(),
                'request_id' => $requestId,
                'mode' => 'url',
                'url' => $this->truncateString($url, 2000),
                'bytes' => strlen($bin),
            ]);

            return $bin;
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

    private function logJsonLine(string $relativePath, string $event, array $data): void
    {
        try {
            $logger = Log::build([
                'driver' => 'single',
                'path' => storage_path($relativePath),
                'level' => 'debug',
            ]);

            $logger->info($event, $data);
        } catch (\Throwable $e) {
            // Never block main flow because of logging.
        }
    }

    private function truncateString(string $value, int $maxChars): string
    {
        $value = (string) $value;
        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return mb_substr($value, 0, max(0, $maxChars - 50)) . "\n...<truncated>...\n";
    }

    private function truncateDeep(mixed $value, int $maxChars): mixed
    {
        if (is_string($value)) {
            return $this->truncateString($value, $maxChars);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->truncateDeep($v, $maxChars);
            }
            return $out;
        }

        return $value;
    }
}

