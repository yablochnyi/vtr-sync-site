<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SiteAuthor;
use App\Models\SiteCategory;
use App\Models\SitePost;
use App\Services\CustomApiService;
use App\Services\WordPressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SyncSitePosts extends Command
{
    protected $signature = 'sync:site-posts {site_id?}';
    protected $description = 'Synchronize posts and categories from sites';

    public function handle()
    {
        $sites = $this->argument('site_id')
            ? Site::where('id', $this->argument('site_id'))->get()
            : Site::all();

        foreach ($sites as $site) {
            $this->syncSite($site);
        }
    }

    private function syncSite(Site $site)
    {
        if ($site->type_api === 'custom') {
            $api = new CustomApiService(
                url: $site->url,
                login: $site->login,
                password: $site->api_key,
            );

            try {
                $this->syncCustomCategories($site, $api);
                $posts = $api->getPosts();
            } catch (\Throwable $e) {
                $this->error("Сайт {$site->name}: ошибка custom API: " . $e->getMessage());
                return;
            }

            foreach ($posts as $post) {
                $localPost = $this->syncCustomPost($site, $post);
                $this->attachCustomPostCategories($site, $post, $localPost);
            }

            $this->info("Сайт {$site->name}: синхронизировано " . count($posts) . " постов (custom).");
            return;
        }

        $wp = new WordPressApiService(
            url: $site->url,
            login: $site->login,
            password: $site->api_key
        );

        $this->syncCategories($site, $wp);

        $posts = $wp->getPosts();

        foreach ($posts as $post) {
            $localPost = $this->syncPost($site, $wp, $post);
            $this->attachPostCategories($site, $post, $localPost);
        }

        $this->info("Сайт {$site->name}: синхронизировано " . count($posts) . " постов.");
    }

    private function syncCategories(Site $site, WordPressApiService $wp)
    {
        $categories = $wp->getAllCategories();

        foreach ($categories as $cat) {
            SiteCategory::updateOrCreate(
                [
                    'site_id' => $site->id,
                    'wp_id' => $cat['id'],
                ],
                [
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'description' => $cat['description'] ?? null,
                ]
            );
        }
    }

    private function syncPost(Site $site, WordPressApiService $wp, array $post): SitePost
    {
        $author = $this->syncAuthor($site, $wp, $post['author'] ?? null);

        $imagePath = $this->downloadImage($site, $wp, $post);

        return SitePost::updateOrCreate(
            [
                'wp_id' => $post['id'],
                'site_id' => $site->id,
            ],
            [
                'image' => $imagePath,
                'site_id' => $site->id,
                'title' => $post['title']['rendered'] ?? '',
                'content' => $post['content']['rendered'] ?? '',
                'slug' => $post['slug'] ?? null,
                'link' => $post['link'] ?? null,
                'status' => $post['status'] ?? 'draft',
                'author_id' => $author?->id,
                'meta' => $post['meta'] ?? [],
                'short_description' => strip_tags($post['excerpt']['rendered'] ?? ''),
            ]
        );
    }

    private function syncCustomPost(Site $site, array $post): SitePost
    {
        $remoteId = $post['id'] ?? null;
        if (!$remoteId) {
            return new SitePost();
        }

        return SitePost::updateOrCreate(
            [
                'wp_id' => $remoteId,
                'site_id' => $site->id,
            ],
            [
                'site_id' => $site->id,
                'title' => $post['title'] ?? '',
                'content' => $post['content'] ?? '',
                'slug' => $post['slug'] ?? null,
                'link' => $post['link'] ?? null,
                'status' => $post['status'] ?? 'draft',
                'meta' => [],
                'short_description' => strip_tags($post['excerpt'] ?? ''),
                'image' => null,
                'author_id' => null,
            ]
        );
    }

    private function syncCustomCategories(Site $site, CustomApiService $api): void
    {
        $categories = $api->getAllCategories();

        foreach ($categories as $cat) {
            SiteCategory::updateOrCreate(
                [
                    'site_id' => $site->id,
                    'wp_id' => $cat['id'] ?? null,
                ],
                [
                    'name' => $cat['name'] ?? '',
                    'slug' => $cat['slug'] ?? '',
                    'description' => $cat['description'] ?? null,
                ]
            );
        }
    }

    private function attachCustomPostCategories(Site $site, array $post, SitePost $localPost): void
    {
        $categoryIds = $post['categories'] ?? [];
        if (!is_array($categoryIds) || $categoryIds === []) {
            return;
        }

        $localCategoryIds = SiteCategory::where('site_id', $site->id)
            ->whereIn('wp_id', $categoryIds)
            ->pluck('id')
            ->toArray();

        $localPost->categories()->sync($localCategoryIds);
    }

    private function syncAuthor(Site $site, WordPressApiService $wp, ?int $authorId)
    {
        if (!$authorId) {
            return null;
        }

        $wpUser = $wp->getUser($authorId);

        if (!$wpUser) {
            return null;
        }

        return SiteAuthor::updateOrCreate(
            [
                'site_id' => $site->id,
                'wp_id' => $wpUser['id'],
            ],
            [
                'name' => $wpUser['name'] ?? '',
                'slug' => $wpUser['slug'] ?? null,
                'url' => $wpUser['url'] ?? null,
                'avatar' => $wpUser['avatar_urls']['96'] ?? null,
            ]
        );
    }

    private function downloadImage(Site $site, WordPressApiService $wp, array $post): ?string
    {
        if (empty($post['featured_media'])) {
            return null;
        }

        $media = $wp->getMedia($post['featured_media']);
        $imageUrl = $media['source_url'] ?? null;

        if (!$imageUrl) {
            return null;
        }

        $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
        $localDir = "sites/{$site->id}/posts/{$post['id']}";
        $localPath = "{$localDir}/{$filename}";

        if (!Storage::disk('public')->exists($localPath)) {
            try {
                $response = Http::get($imageUrl);

                if ($response->ok()) {
                    Storage::disk('public')->put($localPath, $response->body());
                }
            } catch (\Exception $e) {
                $this->error("Ошибка загрузки изображения: {$imageUrl}");
                return null;
            }
        }

        return $localPath;
    }


    private function attachPostCategories(Site $site, array $post, SitePost $localPost)
    {
        $categoryIds = $post['categories'] ?? [];

        $localCategoryIds = SiteCategory::where('site_id', $site->id)
            ->whereIn('wp_id', $categoryIds)
            ->pluck('id')
            ->toArray();

        $localPost->categories()->sync($localCategoryIds);
    }
}
