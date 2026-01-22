<?php

namespace App\Filament\Resources\Promts\Pages;

use App\Filament\Resources\Promts\PromtResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromts extends ListRecords
{
    protected static string $resource = PromtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading(__('Добавить новый промт'))
                ->modalDescription('Управляйте промтами для генерации контента с помощью AI')
                ->createAnother(false),
        ];
    }
}
