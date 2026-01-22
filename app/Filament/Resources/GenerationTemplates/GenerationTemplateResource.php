<?php

namespace App\Filament\Resources\GenerationTemplates;

use App\Filament\Resources\GenerationTemplates\Pages\EditGenerationTemplate;
use App\Filament\Resources\GenerationTemplates\Pages\ListGenerationTemplates;
use App\Filament\Resources\GenerationTemplates\Schemas\GenerationTemplateForm;
use App\Filament\Resources\GenerationTemplates\Tables\GenerationTemplatesTable;
use App\Models\GenerationTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GenerationTemplateResource extends Resource
{
    protected static ?string $model = GenerationTemplate::class;

    protected static ?string $pluralModelLabel = 'Шаблоны генерации';
    protected static UnitEnum|string|null $navigationGroup = 'Генерация';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GenerationTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GenerationTemplatesTable::configure($table);
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
            'index' => ListGenerationTemplates::route('/'),
            'edit' => EditGenerationTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Шаблоны генерации');
    }
}

