<?php

namespace App\Filament\Resources\Permalinks\Schemas;

use App\Models\Site;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PermalinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->label('Сайт (необязательно)')
                    ->options(fn () => Site::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Если задан — ссылка используется только для выбранного сайта.'),
                TextInput::make('title')
                    ->required()
                    ->placeholder('Главная страница')
                    ->maxLength(255)
                    ->label('Название'),
                TextInput::make('url')
                    ->required()
                    ->placeholder('https://example.com')
                    ->maxLength(255)
                    ->label('Адрес ссылки'),
                Toggle::make('open_in_new_tab')
                    ->label('Открывать в новой вкладке')
                    ->default(true),
                Textarea::make('theme')
                    ->label('Тематика / ключевые слова ссылки')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Нужно, чтобы подбирать ссылку по теме статьи.')
                    ->nullable(),
                TextInput::make('short_description')
                    ->required()
                    ->columnSpanFull()
                    ->placeholder('Краткое описание ссылки')
                    ->maxLength(255)
                    ->label('Описание'),
            ]);
    }
}
