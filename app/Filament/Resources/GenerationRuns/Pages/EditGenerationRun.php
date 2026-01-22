<?php

namespace App\Filament\Resources\GenerationRuns\Pages;

use App\Filament\Resources\GenerationRuns\GenerationRunResource;
use Filament\Resources\Pages\EditRecord;

class EditGenerationRun extends EditRecord
{
    protected static string $resource = GenerationRunResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

