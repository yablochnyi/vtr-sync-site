<?php

namespace App\Filament\Resources\Promts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PromtForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255)
                    ->label('Название'),
                Textarea::make('promt')
                ->required()
                ->columnSpanFull()
                ->label('Текст промта')
                ->rows(5)
            ]);
    }
}
