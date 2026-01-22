<?php

namespace App\Filament\Resources\GenerationTemplates\Pages;

use App\Filament\Resources\GenerationTemplates\GenerationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGenerationTemplates extends ListRecords
{
    protected static string $resource = GenerationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Новый шаблон')
                ->createAnother(false),
        ];
    }
}

