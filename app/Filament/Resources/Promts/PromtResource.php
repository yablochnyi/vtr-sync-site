<?php

namespace App\Filament\Resources\Promts;

use App\Filament\Resources\Promts\Pages\CreatePromt;
use App\Filament\Resources\Promts\Pages\EditPromt;
use App\Filament\Resources\Promts\Pages\ListPromts;
use App\Filament\Resources\Promts\Schemas\PromtForm;
use App\Filament\Resources\Promts\Tables\PromtsTable;
use App\Models\Promt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromtResource extends Resource
{
    protected static ?string $model = Promt::class;

    protected static ?string $pluralModelLabel = 'Промты';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Promt';

    public static function form(Schema $schema): Schema
    {
        return PromtForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromtsTable::configure($table);
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
            'index' => ListPromts::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Промты');
    }
}
