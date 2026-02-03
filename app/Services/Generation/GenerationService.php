<?php

namespace App\Services\Generation;

use App\Models\GenerationRun;
use App\Models\Permalink;
use App\Models\SiteCategory;
use App\Models\SitePost;
use App\Services\AiClient;
use App\Services\CustomApiService;
use App\Services\WordPressApiService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerationService
{
    private const TOPIC_LABEL = 'ТЕМА И КЛЮЧЕВОЕ СЛОВО';
    private const LINKS_LABEL = 'ССЫЛКИ ДЛЯ ВСТАВКИ';

    public function __construct(
        protected AiClient $ai,
        protected PromptRenderer $renderer,
        protected JsonResponseParser $json,
        protected LinkInserter $links,
        protected UniquenessChecker $uniq,
    ) {
    }

    public function run(GenerationRun $run): void
    {
        $run->load([
            'template.site',
            'template.queryGroup',
            'template.articlePromt',
            'template.slugPromt',
            'template.categoryPromt',
            'template.rewritePromt',
            'template.author',
        ]);

        $template = $run->template;
        $site = $template->site;

        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'requested' => $template->articles_per_run,
            'generated' => 0,
            'error' => null,
        ]);

        $isCustom = ($site->type_api ?? null) === 'custom';
        $wp = $isCustom ? null : new WordPressApiService(
            url: $site->url,
            login: $site->login,
            password: $site->api_key,
        );

        $custom = $isCustom ? new CustomApiService(
            url: $site->url,
            login: $site->login,
            password: $site->api_key,
        ) : null;

        for ($i = 0; $i < (int) $template->articles_per_run; $i++) {
            $topic = $template->queryGroup->reserveNextTopic();
            if (!$topic) {
                break;
            }

            try {
                $linksToInsert = $this->pickLinksForTopic(
                    siteUrl: $site->url,
                    siteId: $site->id,
                    topic: $topic->topic,
                    internalLastN: (int) $template->internal_links_last_n,
                    internalCount: (int) $template->internal_links_count,
                    permalinksCount: (int) $template->permalinks_count,
                );

                $article = $this->generateArticle($template->articlePromt->promt, $topic->topic, $site->url, $linksToInsert, $run);

                $title = trim((string) ($article['title'] ?? ''));
                $contentHtml = (string) ($article['content_html'] ?? '');
                $excerpt = trim((string) ($article['excerpt'] ?? ''));
                $summary = trim((string) ($article['summary'] ?? ''));

                if ($title === '' || trim($contentHtml) === '') {
                    throw new \RuntimeException('AI did not return required fields (title/content_html).');
                }

                $slug = $this->generateSlug(
                    topic: $topic->topic,
                    title: $title,
                    suggestedSlug: (string) ($article['slug'] ?? ''),
                    slugPromt: $template->slugPromt?->promt,
                    run: $run,
                );

                $selectedCategories = $this->selectCategories(
                    siteId: $site->id,
                    topic: $topic->topic,
                    title: $title,
                    summary: $summary !== '' ? $summary : $excerpt,
                    max: (int) $template->max_categories_per_article,
                    categoryPromt: $template->categoryPromt?->promt,
                    run: $run,
                );

                $localCategoryIds = $isCustom
                    ? $this->ensureCategoriesExistCustom($custom, $site->id, $selectedCategories)
                    : $this->ensureCategoriesExist($wp, $site->id, $selectedCategories);

                $recent = SitePost::query()
                    ->where('site_id', $site->id)
                    ->whereNotNull('content')
                    ->latest('id')
                    ->limit(50)
                    ->pluck('content')
                    ->all();

                $uniqueness = $this->uniq->uniquenessPercent($contentHtml, $recent);

                if ($uniqueness < (int) $template->uniqueness_min_percent) {
                    $rewrite = $template->rewritePromt?->promt;
                    if (is_string($rewrite) && trim($rewrite) !== '') {
                        $rewritten = $this->rewriteArticle($rewrite, $topic->topic, $title, $contentHtml, $run);
                        if ($rewritten !== '') {
                            $contentHtml = $rewritten;
                            $uniqueness = $this->uniq->uniquenessPercent($contentHtml, $recent);
                        }
                    }
                }

                if ($uniqueness < (int) $template->uniqueness_min_percent) {
                    $topic->update([
                        'status' => 'failed',
                        'reserved_at' => null,
                    ]);
                    continue;
                }

                $contentHtml = $this->applyLinking(
                    siteUrl: $site->url,
                    siteId: $site->id,
                    topic: $topic->topic,
                    contentHtml: $contentHtml,
                    internalLastN: (int) $template->internal_links_last_n,
                    internalCount: (int) $template->internal_links_count,
                    permalinksCount: (int) $template->permalinks_count,
                    links: $linksToInsert,
                );

                $authorWpId = null;
                $localAuthorId = null;
                if (!$isCustom && $template->author_mode === 'fixed' && $template->author) {
                    $authorWpId = $template->author->wp_id;
                    $localAuthorId = $template->author->id;
                } elseif (!$isCustom && $template->author_mode === 'auto') {
                    $picked = $site->authors()->inRandomOrder()->first();
                    if ($picked) {
                        $authorWpId = $picked->wp_id;
                        $localAuthorId = $picked->id;
                    }
                }

                $payload = [
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $contentHtml,
                    'excerpt' => $excerpt,
                    'status' => $template->wp_status ?: 'draft',
                ];

                $payload['categories'] = SiteCategory::query()
                    ->whereIn('id', $localCategoryIds)
                    ->pluck('wp_id')
                    ->filter()
                    ->values()
                    ->all();

                if (!$isCustom) {
                    $payload['author'] = $authorWpId;
                }

                $localImagePath = $this->generateAndStoreFeaturedImage(
                    siteId: (int) $site->id,
                    topic: (string) $topic->topic,
                    title: (string) $title,
                    run: $run,
                );

                if ($localImagePath) {
                    try {
                        $localFile = Storage::disk('public')->path($localImagePath);
                        $filename = basename($localFile);

                        if ($isCustom && $custom) {
                            $media = $custom->uploadMedia($localFile, $filename);
                            $payload['featured_image'] = $media['path'] ?? $media['url'] ?? null;
                        } elseif (!$isCustom && $wp) {
                            $media = $wp->uploadMedia($localFile, $filename);
                            $payload['featured_media'] = $media['id'] ?? null;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Featured image upload failed', [
                            'site_id' => $site->id,
                            'run_id' => $run->id,
                            'topic' => $topic->topic,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $payload = Arr::where($payload, fn ($v) => $v !== null && $v !== '');

                $wpPost = $isCustom ? $custom->createPost($payload) : $wp->createPost($payload);

                $wpId = (int) ($wpPost['id'] ?? 0);
                if ($wpId <= 0) {
                    throw new \RuntimeException('Remote API did not return post id.');
                }

                $localPost = SitePost::updateOrCreate(
                    [
                        'site_id' => $site->id,
                        'wp_id' => $wpId,
                    ],
                    [
                        'generation_run_id' => $run->id,
                        'generation_template_id' => $template->id,
                        'query_topic_id' => $topic->id,
                        'is_generated' => true,
                        'title' => $title,
                        'content' => $contentHtml,
                        'slug' => $slug,
                        'status' => $payload['status'] ?? 'draft',
                        'short_description' => $excerpt,
                        'summary' => $summary !== '' ? $summary : null,
                        'link' => $wpPost['link'] ?? null,
                        'author_id' => $localAuthorId,
                        'image' => $localImagePath,
                        'ai_meta' => [
                            'template_id' => $template->id,
                            'run_id' => $run->id,
                            'topic' => $topic->topic,
                            'uniqueness_percent' => $uniqueness,
                            'article_promt_id' => $template->article_promt_id,
                            'slug_promt_id' => $template->slug_promt_id,
                            'category_promt_id' => $template->category_promt_id,
                            'rewrite_promt_id' => $template->rewrite_promt_id,
                        ],
                    ]
                );

                $localPost->categories()->sync($localCategoryIds);

                $topic->markUsed();

                $run->increment('generated');
            } catch (\Throwable $e) {
                $topic->update([
                    'status' => 'failed',
                    'reserved_at' => null,
                ]);
            }
        }

        $run->update([
            'status' => 'completed',
            'finished_at' => now(),
        ]);
    }


    private function generateArticle(string $promt, string $topic, string $siteUrl, array $linksToInsert, GenerationRun $run): array
    {
        $userPrompt = $this->renderer->render($promt, [
            'topic' => $topic,
            'keyword' => $topic,
            'site_url' => $siteUrl,
        ]);
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, self::TOPIC_LABEL);
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'site_url', $siteUrl, 'SITE_URL');

        if ($linksToInsert !== []) {
            $userPrompt = rtrim($userPrompt) . "\n\n"
                . self::LINKS_LABEL . ":\n"
                . $this->formatLinksForPrompt($linksToInsert) . "\n\n"
                . "ТРЕБОВАНИЯ К ССЫЛКАМ:\n"
                . "- Вставь КАЖДУЮ ссылку ровно 1 раз в content_html.\n"
                . "- URL не меняй.\n"
                . "- Анкор используй как дано.\n"
                . "- Встраивай ссылку в предложение/абзац по смыслу, НЕ делай отдельный абзац вида <p><a>...</a></p>.\n";
        }

        $system = 'You are a content generator. Return ONLY valid JSON object with keys: '
            . '"title", "slug", "excerpt", "summary", "content_html". '
            . 'Do not wrap in markdown. content_html must be valid HTML with <p> paragraphs. '
            . 'If user provides "' . self::LINKS_LABEL . '", you MUST integrate those links naturally into content_html paragraphs (not as standalone link-only paragraphs).';

        $resp = $this->ai->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userPrompt],
        ], [
            'context' => [
                'purpose' => 'article',
                'run_id' => $run->id,
                'template_id' => $run->generation_template_id,
                'topic' => $topic,
            ],
        ]);

        return $this->json->parseObject($this->ai->extractFirstMessageContent($resp));
    }

    private function rewriteArticle(string $promt, string $topic, string $title, string $contentHtml, GenerationRun $run): string
    {
        $userPrompt = $this->renderer->render($promt, [
            'topic' => $topic,
            'keyword' => $topic,
            'title' => $title,
            'content_html' => $contentHtml,
        ]);
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, self::TOPIC_LABEL);
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'title', $title, 'ЗАГОЛОВОК');

        $system = 'Return ONLY valid JSON object with keys: "content_html".';

        $resp = $this->ai->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userPrompt],
        ], [
            'context' => [
                'purpose' => 'rewrite',
                'run_id' => $run->id,
                'template_id' => $run->generation_template_id,
                'topic' => $topic,
                'title' => $title,
            ],
        ]);

        $data = $this->json->parseObject($this->ai->extractFirstMessageContent($resp));

        return is_string($data['content_html'] ?? null) ? (string) $data['content_html'] : '';
    }

    private function generateSlug(string $topic, string $title, string $suggestedSlug, ?string $slugPromt, ?GenerationRun $run = null): string
    {
        $slug = trim($suggestedSlug);
        if ($slug !== '') {
            return Str::slug($slug);
        }

        if (is_string($slugPromt) && trim($slugPromt) !== '') {
            $userPrompt = $this->renderer->render($slugPromt, [
                'topic' => $topic,
                'keyword' => $topic,
                'title' => $title,
            ]);
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, self::TOPIC_LABEL);
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'title', $title, 'ЗАГОЛОВОК');

            $system = 'Return ONLY valid JSON object with key: "slug".';

            $resp = $this->ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'context' => array_filter([
                    'purpose' => 'slug',
                    'run_id' => $run?->id,
                    'template_id' => $run?->generation_template_id,
                    'topic' => $topic,
                    'title' => $title,
                ]),
            ]);

            $data = $this->json->parseObject($this->ai->extractFirstMessageContent($resp));
            $slug = trim((string) ($data['slug'] ?? ''));
        }

        if ($slug === '') {
            $slug = Str::slug($title);
        }

        return Str::slug($slug);
    }

    /**
     * @return array<int,string> category names
     */
    private function selectCategories(int $siteId, string $topic, string $title, string $summary, int $max, ?string $categoryPromt, ?GenerationRun $run = null): array
    {
        $max = max(0, $max);
        if ($max === 0) {
            return [];
        }

        $existing = SiteCategory::query()
            ->where('site_id', $siteId)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if (is_string($categoryPromt) && trim($categoryPromt) !== '' && $existing !== []) {
            $userPrompt = $this->renderer->render($categoryPromt, [
                'topic' => $topic,
                'keyword' => $topic,
                'title' => $title,
                'summary' => $summary,
                'categories' => implode("\n", $existing),
                'max' => $max,
            ]);
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, self::TOPIC_LABEL);
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'title', $title, 'ЗАГОЛОВОК');
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'summary', $summary, 'КРАТКО');
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'categories', implode("\n", $existing), 'ДОСТУПНЫЕ РУБРИКИ');
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'max', (string) $max, 'MAX');

            $system = 'Return ONLY valid JSON array of category names (strings), max ' . $max . '.';

            $resp = $this->ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'context' => array_filter([
                    'purpose' => 'categories',
                    'run_id' => $run?->id,
                    'template_id' => $run?->generation_template_id,
                    'topic' => $topic,
                    'title' => $title,
                ]),
            ]);

            $list = $this->json->parseList($this->ai->extractFirstMessageContent($resp));
            $list = array_values(array_filter(array_map(static fn ($x) => trim((string) $x), $list)));

            if ($list !== []) {
                return array_slice($list, 0, $max);
            }
        }

        $hay = mb_strtolower($topic . ' ' . $title . ' ' . $summary);
        $scored = [];
        foreach ($existing as $name) {
            $n = mb_strtolower((string) $name);
            $score = 0;
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $n) ?: [] as $w) {
                $w = trim((string) $w);
                if ($w !== '' && mb_strlen($w) >= 3 && str_contains($hay, $w)) {
                    $score++;
                }
            }
            $scored[] = ['name' => (string) $name, 'score' => $score];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $picked = array_values(array_filter(array_map(fn ($x) => $x['score'] > 0 ? $x['name'] : null, $scored)));

        return array_slice($picked, 0, $max);
    }

    private function appendContextIfMissing(string $prompt, string $key, string $value, string $label): string
    {
        $prompt = rtrim($prompt);
        $value = trim($value);

        if ($value === '') {
            return $prompt;
        }

        // If placeholder exists, user likely placed it intentionally.
        if (preg_match('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/u', $prompt)) {
            return $prompt;
        }

        return $prompt . "\n\n{$label}: {$value}\n";
    }


    private function ensureCategoriesExist(WordPressApiService $wp, int $siteId, array $categoryNames): array
    {
        $localIds = [];

        foreach ($categoryNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $existing = SiteCategory::query()
                ->where('site_id', $siteId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $localIds[] = $existing->id;
                continue;
            }

            $slug = Str::slug($name);
            $wpData = $wp->createCategory(name: $name, slug: $slug, description: null);

            $cat = SiteCategory::create([
                'site_id' => $siteId,
                'wp_id' => $wpData['id'] ?? null,
                'name' => $name,
                'slug' => $wpData['slug'] ?? $slug,
                'description' => $wpData['description'] ?? null,
            ]);

            $localIds[] = $cat->id;
        }

        return $localIds;
    }


    private function ensureCategoriesExistCustom(?CustomApiService $api, int $siteId, array $categoryNames): array
    {
        if (!$api) {
            return [];
        }

        $localIds = [];

        foreach ($categoryNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $existing = SiteCategory::query()
                ->where('site_id', $siteId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $localIds[] = $existing->id;
                continue;
            }

            $slug = Str::slug($name);
            $remote = $api->createCategory(name: $name, slug: $slug, description: null);

            $cat = SiteCategory::create([
                'site_id' => $siteId,
                'wp_id' => $remote['id'] ?? null,
                'name' => $remote['name'] ?? $name,
                'slug' => $remote['slug'] ?? $slug,
                'description' => $remote['description'] ?? null,
            ]);

            $localIds[] = $cat->id;
        }

        return $localIds;
    }

    private function applyLinking(
        string $siteUrl,
        int $siteId,
        string $topic,
        string $contentHtml,
        int $internalLastN,
        int $internalCount,
        int $permalinksCount,
        ?array $links = null,
    ): string {
        $links = $links ?? $this->pickLinksForTopic(
            siteUrl: $siteUrl,
            siteId: $siteId,
            topic: $topic,
            internalLastN: $internalLastN,
            internalCount: $internalCount,
            permalinksCount: $permalinksCount,
        );

        return $this->links->insert($contentHtml, $links);
    }


    private function pickLinksForTopic(
        string $siteUrl,
        int $siteId,
        string $topic,
        int $internalLastN,
        int $internalCount,
        int $permalinksCount,
    ): array {
        $links = [];

        $internalCount = max(0, $internalCount);
        $internalLastN = max(0, $internalLastN);
        $permalinksCount = max(0, $permalinksCount);

        if ($internalCount > 0 && $internalLastN > 0) {
            $internalLastN = max($internalLastN, $internalCount);

            $candidates = SitePost::query()
                ->where('site_id', $siteId)
                ->whereIn('status', ['publish', 'published'])
                ->where(function ($q) {
                    $q->whereNotNull('slug')->orWhereNotNull('link');
                })
                ->whereNotNull('title')
                ->orderByDesc('wp_id')
                ->limit($internalLastN)
                ->get();

            $scored = $candidates->map(function (SitePost $p) use ($topic) {
                $hay = mb_strtolower($topic);
                $title = mb_strtolower((string) $p->title);
                $score = 0;
                foreach (preg_split('/[^\p{L}\p{N}]+/u', $hay) ?: [] as $w) {
                    $w = trim((string) $w);
                    if ($w !== '' && mb_strlen($w) >= 3 && str_contains($title, $w)) {
                        $score++;
                    }
                }
                return ['post' => $p, 'score' => $score];
            })->sortByDesc('score')->values();

            foreach ($scored->take($internalCount) as $row) {
                /** @var SitePost $p */
                $p = $row['post'];
                $url = $p->link ?: rtrim($siteUrl, '/') . '/' . ltrim((string) $p->slug, '/');
                $links[] = [
                    'url' => $url,
                    'anchor' => (string) $p->title,
                    'new_tab' => false,
                ];
            }
        }

        if ($permalinksCount > 0) {
            $permalinks = Permalink::query()
                ->where(function ($q) use ($siteId) {
                    $q->whereNull('site_id')->orWhere('site_id', $siteId);
                })
                ->get()
                ->map(function (Permalink $pl) use ($topic) {
                    $hay = mb_strtolower($topic);
                    $basis = mb_strtolower(trim((string) $pl->title . ' ' . (string) ($pl->theme ?? '')));
                    $score = 0;
                    foreach (preg_split('/[^\p{L}\p{N}]+/u', $basis) ?: [] as $w) {
                        $w = trim((string) $w);
                        if ($w !== '' && mb_strlen($w) >= 3 && str_contains($hay, $w)) {
                            $score++;
                        }
                    }
                    return ['pl' => $pl, 'score' => $score];
                })
                ->sortByDesc('score')
                ->values()
                ->take($permalinksCount);

            foreach ($permalinks as $row) {
                /** @var Permalink $pl */
                $pl = $row['pl'];
                $links[] = [
                    'url' => (string) $pl->url,
                    'anchor' => (string) $pl->title,
                    'new_tab' => (bool) ($pl->open_in_new_tab ?? true),
                ];
            }
        }

        return $links;
    }

    private function formatLinksForPrompt(array $links): string
    {
        $lines = [];
        foreach ($links as $l) {
            $url = (string) ($l['url'] ?? '');
            $anchor = (string) ($l['anchor'] ?? '');
            $newTab = (bool) ($l['new_tab'] ?? false);
            if ($url === '' || $anchor === '') {
                continue;
            }
            $lines[] = '- ' . $anchor . ' => ' . $url . ($newTab ? ' (new_tab: yes)' : ' (new_tab: no)');
        }

        return implode("\n", $lines);
    }

    private function generateAndStoreFeaturedImage(int $siteId, string $topic, string $title, GenerationRun $run): ?string
    {
        try {
            $prompt = trim(
                "Create a clean, modern featured image for a crypto blog article.\n"
                . "Topic: {$topic}\n"
                . "Title: {$title}\n"
                . "No text in the image. Abstract tech / blockchain vibe. High contrast. Professional."
            );

            $bin = $this->ai->generateImagePng($prompt, [
                'size' => '1024x1024',
            ]);

            $dir = "sites/{$siteId}/generated";
            $name = 'featured-' . Str::uuid()->toString() . '.png';
            $path = "{$dir}/{$name}";

            Storage::disk('public')->put($path, $bin);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Featured image generation failed', [
                'site_id' => $siteId,
                'run_id' => $run->id,
                'topic' => $topic,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

