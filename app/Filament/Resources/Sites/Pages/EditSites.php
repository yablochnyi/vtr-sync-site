<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\SyncSitePostsJob;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;

class EditSites extends EditRecord
{
    protected static string $resource = SiteResource::class;
    public function getTitle(): string|Htmlable
    {
        return __('Управление');
    }
    protected function getHeaderActions(): array
    {
        return [
//            Action::make('sync')
//                ->label('Синхронизировать')
//                ->icon('heroicon-o-arrow-path')
//                ->action(function () {
//                    SyncSitePostsJob::dispatch($this->record->id);
//                })
        ];
    }
}
