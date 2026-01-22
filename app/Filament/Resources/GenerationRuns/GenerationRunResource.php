<?php

namespace App\Filament\Resources\GenerationRuns;

use App\Filament\Resources\GenerationRuns\Pages\EditGenerationRun;
use App\Filament\Resources\GenerationRuns\Pages\ListGenerationRuns;
use App\Filament\Resources\GenerationRuns\RelationManagers\PostsRelationManager;
use App\Filament\Resources\GenerationRuns\Tables\GenerationRunsTable;
use App\Models\GenerationRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GenerationRunResource extends Resource
{
    protected static ?string $model = GenerationRun::class;

    protected static ?string $pluralModelLabel = 'История генераций';
    protected static UnitEnum|string|null $navigationGroup = 'Генерация';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return GenerationRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PostsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGenerationRuns::route('/'),
            'edit' => EditGenerationRun::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('История генераций');
    }
}

