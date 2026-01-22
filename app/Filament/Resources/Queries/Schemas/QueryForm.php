<?php

namespace App\Filament\Resources\Queries\Schemas;

use App\Models\Site;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QueryForm
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
                    ->helperText('Если задан — темы будут использоваться только для этого сайта.'),
                TextInput::make('title')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255)
                    ->label('Название группы')
                    ->helperText('Темы добавляются ниже, во вкладке "Темы".'),
            ]);
    }
}
