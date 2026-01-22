<?php

namespace App\Filament\Resources\Sites;


use App\Filament\Resources\Sites\Pages\EditSites;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Sites\RelationManagers\CategoriesRelationManager;
use App\Filament\Resources\Sites\RelationManagers\PostsRelationManager;
use App\Filament\Resources\Sites\Schemas\SiteForm;
use App\Filament\Resources\Sites\Tables\SitesTable;
use App\Models\Site;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;
    protected static ?string $pluralModelLabel = 'Сайты';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Site';

    public static function form(Schema $schema): Schema
    {
        return SiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CategoriesRelationManager::make(),
            PostsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'edit' => EditSites::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Сайты');
    }
}
