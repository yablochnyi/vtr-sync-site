<?php

namespace App\Filament\Resources\GenerationRuns\RelationManagers;

use App\Models\SiteCategory;
use App\Models\SitePost;
use App\Services\CustomApiService;
use App\Services\WordPressApiService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Статьи прогона');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageSize(44)
                    ->square()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->label('Статус'),
                TextColumn::make('link')
                    ->label('URL')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ai_meta.uniqueness_percent')
                    ->label('Уникальность')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->since(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('Открыть / Редактировать')
                    ->icon('heroicon-m-eye')
                    ->modalHeading(fn (SitePost $record) => (string) ($record->title ?? 'Статья'))
                    ->modalSubmitActionLabel('Сохранить')
                    ->modalCancelActionLabel('Закрыть')
                    ->fillForm(function (SitePost $record): array {
                        $record->loadMissing(['site', 'categories']);

                        return [
                            'title' => (string) ($record->title ?? ''),
                            'slug' => (string) ($record->slug ?? ''),
                            'short_description' => (string) ($record->short_description ?? ''),
                            'status' => (string) ($record->status ?? 'draft'),
                            'content' => (string) ($record->content ?? ''),
                            'image' => (string) ($record->image ?? ''),
                            'categories' => $record->categories()->pluck('site_categories.id')->all(),
                        ];
                    })
                    ->form([
                        Placeholder::make('url')
                            ->label('URL')
                            ->content(function (SitePost $record) {
                                $url = $this->resolvePostUrl($record);
                                if ($url === null) {
                                    return new HtmlString('<span class="text-gray-500">Нет URL</span>');
                                }

                                $eUrl = e($url);
                                return new HtmlString("<a href=\"{$eUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$eUrl}</a>");
                            }),
                        TextInput::make('title')
                            ->required()
                            ->label('Заголовок')
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->label('URL')
                            ->maxLength(255),
                        Textarea::make('short_description')
                            ->required()
                            ->label('Краткое описание')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->rows(3),
                        Select::make('status')
                            ->label('Статус')
                            ->required()
                            ->options([
                                'draft' => 'draft',
                                'publish' => 'publish',
                            ])
                            ->default('draft')
                            ->helperText('draft = черновик, publish = опубликовано'),
                        RichEditor::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->label('Содержание статьи'),
                        FileUpload::make('image')
                            ->label('Загрузить новое изображение')
                            ->image()
                            ->disk('public')
                            ->directory(function (?SitePost $record) {
                                $siteId = $record?->site_id ?? 0;
                                $postWpId = $record?->wp_id ?? 'new';

                                return "sites/{$siteId}/posts/{$postWpId}";
                            })
                            ->columnSpanFull(),
                        CheckboxList::make('categories')
                            ->label('Категории')
                            ->options(function (SitePost $record) {
                                return SiteCategory::query()
                                    ->where('site_id', (int) $record->site_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->columnSpanFull()
                            ->columns(3)

                    ])
                    ->action(function (SitePost $record, array $data) {
                        $record->loadMissing(['site']);

                        $status = (string) ($data['status'] ?? 'draft');
                        if (!in_array($status, ['draft', 'publish'], true)) {
                            $status = 'draft';
                        }

                        $record->update([
                            'title' => (string) ($data['title'] ?? $record->title),
                            'slug' => (string) ($data['slug'] ?? $record->slug),
                            'short_description' => (string) ($data['short_description'] ?? $record->short_description),
                            'status' => $status,
                            'content' => (string) ($data['content'] ?? $record->content),
                            'image' => (string) ($data['image'] ?? $record->image),
                        ]);

                        $categoryIds = array_values(array_filter(array_map('intval', (array) ($data['categories'] ?? []))));
                        $record->categories()->sync($categoryIds);

                        $this->syncToRemote($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    private function syncToRemote(SitePost $post): void
    {
        $post->loadMissing(['site', 'categories', 'author']);

        $site = $post->site;
        if (!$site) {
            return;
        }

        if (($site->type_api ?? null) === 'custom') {
            $api = new CustomApiService(
                url: (string) $site->url,
                login: (string) $site->login,
                password: (string) $site->api_key,
            );

            $payload = [
                'title' => (string) $post->title,
                'slug' => (string) $post->slug,
                'content' => (string) $post->content,
                'excerpt' => (string) $post->short_description,
                'status' => (string) ($post->status ?? 'draft'),
                'categories' => $post->categories->pluck('wp_id')->filter()->values()->all(),
            ];

            $remote = $post->wp_id
                ? $api->updatePost((int) $post->wp_id, $payload)
                : $api->createPost($payload);

            if (!$post->wp_id && isset($remote['id'])) {
                $post->update([
                    'wp_id' => $remote['id'],
                    'link' => $remote['link'] ?? null,
                ]);
            } elseif (isset($remote['link'])) {
                $post->update(['link' => $remote['link']]);
            }

            return;
        }

        $wp = new WordPressApiService(
            (string) $site->url,
            (string) $site->login,
            (string) $site->api_key,
        );

        $featuredMediaId = null;
        if ($post->image && Storage::disk('public')->exists($post->image)) {
            $localFile = Storage::disk('public')->path($post->image);
            $filename = basename($localFile);
            $media = $wp->uploadMedia($localFile, $filename);
            $featuredMediaId = $media['id'] ?? null;
        }

        $payload = [
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'content' => (string) $post->content,
            'excerpt' => (string) $post->short_description,
            'status' => (string) ($post->status ?? 'draft'),
            'categories' => $post->categories->pluck('wp_id')->filter()->values()->all(),
            'featured_media' => $featuredMediaId,
            'author' => $post->author?->wp_id,
        ];

        $payload = array_filter($payload, fn ($v) => $v !== null && $v !== '');

        $remote = $post->wp_id
            ? $wp->updatePost((int) $post->wp_id, $payload)
            : $wp->createPost($payload);

        if (!$post->wp_id && isset($remote['id'])) {
            $post->update([
                'wp_id' => $remote['id'],
                'link' => $remote['link'] ?? null,
            ]);
        } elseif (isset($remote['link'])) {
            $post->update(['link' => $remote['link']]);
        }
    }

    private function resolvePostUrl(SitePost $post): ?string
    {
        if (is_string($post->link) && trim($post->link) !== '') {
            return trim($post->link);
        }

        $siteUrl = (string) ($post->site?->url ?? '');
        $slug = (string) ($post->slug ?? '');
        if (trim($siteUrl) === '' || trim($slug) === '') {
            return null;
        }

        return rtrim($siteUrl, '/') . '/' . ltrim($slug, '/');
    }

    // Note: if later we add HTML-rendered previews, add sanitization here.
}

