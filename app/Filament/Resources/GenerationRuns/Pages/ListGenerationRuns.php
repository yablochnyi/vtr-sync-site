<?php

namespace App\Filament\Resources\GenerationRuns\Pages;

use App\Filament\Resources\GenerationRuns\GenerationRunResource;
use Filament\Resources\Pages\ListRecords;

class ListGenerationRuns extends ListRecords
{
    protected static string $resource = GenerationRunResource::class;
}

