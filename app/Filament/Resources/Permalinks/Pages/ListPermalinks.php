<?php

namespace App\Filament\Resources\Permalinks\Pages;

use App\Filament\Resources\Permalinks\PermalinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermalinks extends ListRecords
{
    protected static string $resource = PermalinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading(__('Настройки постоянных ссылок'))
                ->modalDescription('Управление постоянными ссылками для использования в статьях')
                ->createAnother(false),
        ];
    }
}
