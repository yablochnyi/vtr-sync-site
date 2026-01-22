<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CustomApiService
{
    public function __construct(protected string $url, protected string $login, protected string $password)
    {
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getPosts(): array
    {
        $page = 1;
        $posts = [];

        do {
            $response = Http::timeout(60)
                ->acceptJson()
                ->withBasicAuth($this->login, $this->password)
                ->get(rtrim($this->url, '/') . '/api/v1/posts', [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException("Custom API getPosts failed: {$response->status()} {$response->body()}");
            }

            $json = (array) $response->json();
            $data = $json['data'] ?? $json;
            if (is_array($data) && array_is_list($data)) {
                $posts = array_merge($posts, $data);
            }

            $meta = $json['meta'] ?? [];
            $lastPage = (int) ($meta['last_page'] ?? $page);

            $page++;
        } while ($page <= $lastPage);

        return $posts;
    }


    public function getAllCategories(): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->get(rtrim($this->url, '/') . '/api/v1/categories');

        if ($response->failed()) {
            throw new \RuntimeException("Custom API getAllCategories failed: {$response->status()} {$response->body()}");
        }

        $json = (array) $response->json();
        $data = $json['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    public function createCategory(string $name, string $slug = null, string $description = null): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->post(rtrim($this->url, '/') . '/api/v1/categories', [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);

        if ($response->failed()) {
            throw new \Exception("Ошибка создания категории в Custom API: " . $response->body());
        }

        return (array) $response->json();
    }

    public function updateCategory(int $id, string $name, ?string $slug, ?string $description): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->post(rtrim($this->url, '/') . "/api/v1/categories/{$id}", [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);

        if ($response->failed()) {
            throw new \Exception("Ошибка обновления категории в Custom API: " . $response->body());
        }

        return (array) $response->json();
    }

    public function createPost(array $data): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->post(rtrim($this->url, '/') . '/api/v1/posts', $data);

        if ($response->failed()) {
            throw new \Exception("Ошибка создания поста в Custom API: " . $response->body());
        }

        return (array) $response->json();
    }

    public function updatePost(int $id, array $data): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->post(rtrim($this->url, '/') . "/api/v1/posts/{$id}", $data);

        if ($response->failed()) {
            throw new \Exception("Ошибка обновления поста в Custom API: " . $response->body());
        }

        return (array) $response->json();
    }

    public function deletePost(int $id): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->delete(rtrim($this->url, '/') . "/api/v1/posts/{$id}");

        if ($response->failed()) {
            throw new \Exception("Ошибка удаления поста в Custom API: " . $response->body());
        }

        return (array) $response->json();
    }
}

