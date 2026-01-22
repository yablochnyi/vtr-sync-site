<?php

namespace App\Filament\Resources\Permalinks;

use App\Filament\Resources\Permalinks\Pages\CreatePermalink;
use App\Filament\Resources\Permalinks\Pages\EditPermalink;
use App\Filament\Resources\Permalinks\Pages\ListPermalinks;
use App\Filament\Resources\Permalinks\Schemas\PermalinkForm;
use App\Filament\Resources\Permalinks\Tables\PermalinksTable;
use App\Models\Permalink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PermalinkResource extends Resource
{
    protected static ?string $model = Permalink::class;
    protected static ?string $pluralModelLabel = 'Постоянные ссылки';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Permalink';

    public static function form(Schema $schema): Schema
    {
        return PermalinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermalinksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermalinks::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Постоянные ссылки');
    }
}
