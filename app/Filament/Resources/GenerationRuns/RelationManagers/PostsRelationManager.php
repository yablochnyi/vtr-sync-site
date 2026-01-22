<?php

namespace App\Filament\Resources\GenerationRuns\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
            ->recordActions([])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}

