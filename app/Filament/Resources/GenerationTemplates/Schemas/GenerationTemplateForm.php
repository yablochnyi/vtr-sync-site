<?php

namespace App\Filament\Resources\GenerationTemplates\Schemas;

use App\Models\Promt;
use App\Models\QueryGroup;
use App\Models\Site;
use App\Models\SiteAuthor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GenerationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основное')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->required()
                        ->label('Название шаблона')
                        ->columnSpanFull(),
                    Select::make('site_id')
                        ->required()
                        ->label('Сайт')
                        ->options(fn () => Site::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Select::make('query_group_id')
                        ->required()
                        ->label('Группа тем')
                        ->options(fn () => QueryGroup::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable()
                        ->preload(),
                ]),
            Section::make('Промты')
                ->columns(2)
                ->components([
                    Select::make('article_promt_id')
                        ->required()
                        ->label('Промт статьи')
                        ->options(fn () => Promt::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Select::make('slug_promt_id')
                        ->nullable()
                        ->label('Промт для ссылки (slug)')
                        ->options(fn () => Promt::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Select::make('category_promt_id')
                        ->nullable()
                        ->label('Промт для выбора рубрик')
                        ->options(fn () => Promt::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Select::make('rewrite_promt_id')
                        ->nullable()
                        ->label('Промт для рерайта (если не уникально)')
                        ->options(fn () => Promt::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable()
                        ->preload(),
                ]),
            Section::make('Параметры')
                ->columns(3)
                ->components([
                    TextInput::make('articles_per_run')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->label('Статей за прогон'),
                    TextInput::make('uniqueness_min_percent')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->label('Мин. уникальность (%)'),
                    Select::make('wp_status')
                        ->required()
                        ->label('Статус')
                        ->options([
                            'draft' => 'draft',
                            'publish' => 'publish',
                        ])
                        ->default('draft'),

                    TextInput::make('max_categories_per_article')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10)
                        ->required()
                        ->label('Макс. рубрик на статью'),
                    TextInput::make('internal_links_count')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->required()
                        ->label('Внутренних ссылок'),
                    TextInput::make('internal_links_last_n')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(500)
                        ->required()
                        ->label('Искать в последних N постах'),

                    TextInput::make('permalinks_count')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(20)
                        ->required()
                        ->label('Постоянных ссылок'),
                ]),
            Section::make('Автор')
                ->columns(2)
                ->components([
                    Select::make('author_mode')
                        ->required()
                        ->label('Режим автора')
                        ->options([
                            'auto' => 'auto (случайный автор сайта)',
                            'fixed' => 'fixed (выбранный автор)',
                            'none' => 'none',
                        ])
                        ->default('auto')
                        ->reactive(),
                    Select::make('author_id')
                        ->nullable()
                        ->label('Автор')
                        ->options(fn () => SiteAuthor::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn ($get) => $get('author_mode') === 'fixed'),
                ]),
        ]);
    }
}

