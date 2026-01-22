<?php

namespace App\Filament\Resources\Promts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromtsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->contentGrid([
                'md' => 1,
                'lg' => 1,
            ])
            ->columns([
                Panel::make([
                    Stack::make([
                        TextColumn::make('title')
                            ->label('')
                            ->weight('bold')
                            ->size('lg'),

                        TextColumn::make('promt')
                            ->label('')
                            ->limit(200)
                            ->markdown()
                            ->extraAttributes(['class' => 'text-gray-600']),
                    ]),
                ])
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('Промт'))
                    ->modalDescription('Управляйте промтами для генерации контента с помощью AI')
                    ->label('Редактировать'),
                DeleteAction::make()->label('Удалить')->color('danger'),
            ]);
    }
}
