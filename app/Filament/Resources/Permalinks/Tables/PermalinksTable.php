<?php

namespace App\Filament\Resources\Permalinks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermalinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    TextColumn::make('title')
                        ->weight(FontWeight::Bold),
                    TextColumn::make('url'),
                    TextColumn::make('site.name')
                        ->label('Сайт')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('theme')
                        ->label('Тематика')
                        ->limit(60)
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('short_description')
                ])
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('Настройки постоянных ссылок'))
                    ->modalDescription('Управление постоянными ссылками для использования в статьях'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
//                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
