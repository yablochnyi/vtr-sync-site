<?php

namespace App\Filament\Resources\GenerationRuns\Tables;

use App\Filament\Resources\GenerationRuns\GenerationRunResource;
use App\Models\GenerationRun;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GenerationRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Прогон')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->label('Статус'),
                TextColumn::make('generated')
                    ->label('Сгенерировано'),
                TextColumn::make('requested')
                    ->label('План'),
                TextColumn::make('started_at')
                    ->label('Старт')
                    ->since(),
                TextColumn::make('finished_at')
                    ->label('Финиш')
                    ->since(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Открыть')
                    ->url(fn (GenerationRun $record) => GenerationRunResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}

