<?php

namespace App\Filament\Resources\Queries;

use App\Filament\Resources\Queries\Pages\ListQueries;
use App\Filament\Resources\Queries\Pages\EditQuery;
use App\Filament\Resources\Queries\RelationManagers\TopicsRelationManager;
use App\Filament\Resources\Queries\Schemas\QueryForm;
use App\Filament\Resources\Queries\Tables\QueriesTable;
use App\Models\QueryGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QueryResource extends Resource
{
    protected static ?string $model = QueryGroup::class;
    protected static ?string $pluralModelLabel = 'Настройки ключевых слов';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return QueryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QueriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TopicsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQueries::route('/'),
            'edit' => EditQuery::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Ключевые слова');
    }
}
