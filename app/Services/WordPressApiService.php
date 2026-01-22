<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WordPressApiService
{
    public function __construct(protected string $url, protected string $login, protected string $password)
    {
    }

    public function getPosts()
    {
        $page = 1;
        $posts = [];

        do {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->get($this->url . "/wp-json/wp/v2/posts", [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if ($response->failed()) {
                break;
            }

            $data = $response->json();
            $posts = array_merge($posts, $data);

            $totalPages = (int)$response->header('X-WP-TotalPages');

            $page++;

        } while ($page <= $totalPages);

        return $posts;
    }

    public function getAllCategories(): array
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->get($this->url . "/wp-json/wp/v2/categories?per_page=100");

        if ($response->failed()) {
            return [];
        }

        return $response->json();
    }

    public function getMedia($id)
    {
        if (!$id) return null;

        $response = Http::withBasicAuth($this->login, $this->password)
            ->get($this->url . "/wp-json/wp/v2/media/{$id}");

        return $response->json();
    }

    public function getUser($id)
    {
        if (!$id) return null;

        $response = Http::withBasicAuth($this->login, $this->password)
            ->get($this->url . "/wp-json/wp/v2/users/{$id}");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    public function createCategory(string $name, string $slug = null, string $description = null)
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->post($this->url . "/wp-json/wp/v2/categories", [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);

        if ($response->failed()) {
            throw new \Exception("Ошибка создания категории в WP: " . $response->body());
        }

        return $response->json();
    }

    public function updateCategory(int $wpId, string $name, ?string $slug, ?string $description)
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->post($this->url . "/wp-json/wp/v2/categories/{$wpId}", [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);

        if ($response->failed()) {
            throw new \Exception("Ошибка обновления категории в WP: " . $response->body());
        }

        return $response->json();
    }

    public function createPost(array $data)
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->post($this->url . "/wp-json/wp/v2/posts", $data);

        if ($response->failed()) {
            throw new \Exception("Ошибка создания поста в WP: " . $response->body());
        }

        return $response->json();
    }

    public function updatePost(int $wpId, array $data)
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->post($this->url . "/wp-json/wp/v2/posts/{$wpId}", $data);

        if ($response->failed()) {
            throw new \Exception("Ошибка обновления поста в WP: " . $response->body());
        }

        return $response->json();
    }

    public function uploadMedia(string $filePath, string $fileName)
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->attach('file', file_get_contents($filePath), $fileName)
            ->post($this->url . "/wp-json/wp/v2/media");

        if ($response->failed()) {
            throw new \Exception("Ошибка загрузки медиа в WP: " . $response->body());
        }

        return $response->json();
    }

    public function deletePost(int $wpId)
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->delete($this->url . "/wp-json/wp/v2/posts/{$wpId}?force=true");

        if ($response->failed()) {
            throw new \Exception("Ошибка удаления поста в WP: " . $response->body());
        }

        return $response->json();
    }

}
