<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('type_api')
                    ->inline()
                    ->hiddenLabel()
                    ->required()
                    ->columnSpanFull()
                    ->reactive()
                    ->default('wordpress')
                    ->options([
                        'wordpress' => 'WordPress',
                        'custom' => 'Custom API',
                    ]),
                TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->label('Название сайта')
                    ->placeholder('Мой сайт'),
                TextInput::make('url')
                    ->required()
                    ->columnSpanFull()
                    ->label('URL сайта')
                    ->placeholder('https://example.com'),
                TextInput::make('login')
                    ->required()
                    ->columnSpanFull()
                    ->label('Логин')
                    ->placeholder('Admin'),
                TextInput::make('api_key')
                    ->required()
                    ->password()
                    ->columnSpanFull()
                    ->label(fn ($get) => $get('type_api') === 'wordpress' ? 'Application Password' : 'Пароль')
                    ->placeholder(fn ($get) => $get('type_api') === 'wordpress' ? 'xxxx xxxx xxxx xxxx xxxx xxxx' : '••••••••')
                    ->helperText(fn ($get) => $get('type_api') === 'wordpress' ? 'Application Password из WordPress Admin → Профиль → Application Passwords' : '')

            ]);
    }
}
