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
use Illuminate\Support\Str;

class GenerationService
{
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
                $article = $this->generateArticle($template->articlePromt->promt, $topic->topic, $site->url, $run);

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

                $contentHtml = $this->applyLinking(
                    siteUrl: $site->url,
                    siteId: $site->id,
                    topic: $topic->topic,
                    contentHtml: $contentHtml,
                    internalLastN: (int) $template->internal_links_last_n,
                    internalCount: (int) $template->internal_links_count,
                    permalinksCount: (int) $template->permalinks_count,
                );

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

    private function generateArticle(string $promt, string $topic, string $siteUrl, GenerationRun $run): array
    {
        $userPrompt = $this->renderer->render($promt, [
            'topic' => $topic,
            'site_url' => $siteUrl,
        ]);
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, 'ТЕМА');
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'site_url', $siteUrl, 'SITE_URL');

        $system = 'You are a content generator. Return ONLY valid JSON object with keys: '
            . '"title", "slug", "excerpt", "summary", "content_html". '
            . 'Do not wrap in markdown. content_html must be valid HTML with <p> paragraphs.';

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
            'title' => $title,
            'content_html' => $contentHtml,
        ]);
        $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, 'ТЕМА');
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
                'title' => $title,
            ]);
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, 'ТЕМА');
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
                'title' => $title,
                'summary' => $summary,
                'categories' => implode("\n", $existing),
                'max' => $max,
            ]);
            $userPrompt = $this->appendContextIfMissing($userPrompt, 'topic', $topic, 'ТЕМА');
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
    ): string {
        $links = [];

        $internalCount = max(0, $internalCount);
        $internalLastN = max(0, $internalLastN);
        $permalinksCount = max(0, $permalinksCount);

        if ($internalCount > 0 && $internalLastN > 0) {
            $candidates = SitePost::query()
                ->where('site_id', $siteId)
                ->whereNotNull('slug')
                ->where('status', 'publish')
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

        return $this->links->insert($contentHtml, $links);
    }
}

