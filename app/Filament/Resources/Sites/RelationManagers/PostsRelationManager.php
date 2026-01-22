<?php

namespace App\Filament\Resources\Sites\RelationManagers;

use App\Services\CustomApiService;
use App\Services\WordPressApiService;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Статьи');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->visible(fn () => ($this->ownerRecord->type_api ?? null) === 'wordpress')
                    ->directory(function ($record) {
                        $siteId = $this->ownerRecord->id;
                        $postWpId = $record?->wp_id ?? 'new';

                        return "sites/{$siteId}/posts/{$postWpId}";
                    })
                    ->columnSpanFull(),


                CheckboxList::make('categories')
                    ->label('Категории')
                    ->relationship(
                        name: 'categories',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('site_id', $this->ownerRecord->id),
                    )
                    ->columnSpanFull()
                    ->columns(3)
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalHeading(__('Создать статью'))
                    ->modalDescription('Выберите тип сайта и введите данные для подключения')
                    ->createAnother(false)
                    ->after(function ($record) {
                        $this->syncToRemote($record);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function ($record) {
                        $this->syncToRemote($record);
                    }),
                DeleteAction::make()
                    ->before(function ($record) {
                        $this->deletePostInRemote($record);
                        $this->deleteLocalPostDirectory($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ]);
    }

    private function syncToRemote($post)
    {
        $site = $this->ownerRecord;
        if (($site->type_api ?? null) === 'custom') {
            $api = new CustomApiService(
                url: $site->url,
                login: $site->login,
                password: $site->api_key
            );

            $payload = [
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'excerpt' => $post->short_description,
                'status' => $post->status ?? 'draft',
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
            }

            return;
        }

        $wp = new WordPressApiService(
            $site->url,
            $site->login,
            $site->api_key
        );

        $featuredMediaId = null;

        if ($post->image && Storage::disk('public')->exists($post->image)) {

            $localFile = Storage::disk('public')->path($post->image);
            $filename = basename($localFile);

            $media = $wp->uploadMedia($localFile, $filename);
            $featuredMediaId = $media['id'];
        }

        $categoryWpIds = $post->categories->pluck('wp_id')->toArray();

        $payload = [
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->short_description,
            'status' => $post->status ?? 'publish',
            'categories' => $categoryWpIds,
            'featured_media' => $featuredMediaId,
            'author' => $post->author?->wp_id,
        ];

        if ($post->wp_id) {
            $wpPost = $wp->updatePost($post->wp_id, $payload);
        } else {
            $wpPost = $wp->createPost($payload);

            $post->update([
                'wp_id' => $wpPost['id'],
                'link' => $wpPost['link'] ?? null,
            ]);

            $this->moveImageDirectoryAfterCreation($post);
        }
    }

    private function moveImageDirectoryAfterCreation($post)
    {
        $siteId = $post->site_id;
        $newPath = "sites/{$siteId}/posts/new";
        $finalPath = "sites/{$siteId}/posts/{$post->wp_id}";

        if (!Storage::disk('public')->exists($newPath)) {
            return;
        }

        Storage::disk('public')->makeDirectory($finalPath);

        foreach (Storage::disk('public')->files($newPath) as $file) {
            $filename = basename($file);
            Storage::disk('public')->move($file, "{$finalPath}/{$filename}");

            if ($post->image === $file) {
                $post->update([
                    'image' => "{$finalPath}/{$filename}"
                ]);
            }
        }

        Storage::disk('public')->deleteDirectory($newPath);
    }

    private function deletePostInRemote($post)
    {
        if (!$post->wp_id) {
            return;
        }

        $site = $this->ownerRecord;

        try {
            if (($site->type_api ?? null) === 'custom') {
                $api = new CustomApiService(
                    url: $site->url,
                    login: $site->login,
                    password: $site->api_key
                );
                $api->deletePost((int) $post->wp_id);
                return;
            }

            $wp = new WordPressApiService(
                $site->url,
                $site->login,
                $site->api_key
            );

            $wp->deletePost($post->wp_id);
        } catch (\Exception $e) {
        }
    }

    private function deleteLocalPostDirectory($post)
    {
        $siteId = $post->site_id;
        $path = "sites/{$siteId}/posts/{$post->wp_id}";

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->deleteDirectory($path);
        }
    }

}
