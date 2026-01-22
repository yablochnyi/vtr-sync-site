<?php

namespace App\Filament\Resources\Queries\Pages;

use App\Filament\Resources\Queries\QueryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQueries extends ListRecords
{
    protected static string $resource = QueryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading(__('Новая группа'))
                ->modalDescription('Добавить группу ключевых слов')
                ->createAnother(false),
        ];
    }
}
