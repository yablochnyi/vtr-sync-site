<?php

namespace App\Filament\Resources\GenerationTemplates\Tables;

use App\Filament\Resources\GenerationRuns\GenerationRunResource;
use App\Filament\Resources\GenerationTemplates\GenerationTemplateResource;
use App\Jobs\RunGenerationJob;
use App\Models\GenerationRun;
use App\Models\GenerationTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GenerationTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Panel::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->label('')
                            ->weight('bold')
                            ->size('lg'),
                        TextColumn::make('site.name')
                            ->label('')
                            ->color('gray'),
                        TextColumn::make('queryGroup.title')
                            ->label('')
                            ->color('gray')
                            ->limit(80),
                    ]),
                ]),
            ])
            ->recordActions([
                Action::make('run')
                    ->label('Запустить')
                    ->icon('heroicon-o-play')
                    ->action(function (GenerationTemplate $record) {
                        $run = GenerationRun::create([
                            'generation_template_id' => $record->id,
                            'name' => $record->name . ' — ' . now()->format('Y-m-d H:i:s'),
                            'status' => 'queued',
                            'requested' => $record->articles_per_run,
                            'generated' => 0,
                            'meta' => [
                                'template_snapshot' => $record->toArray(),
                            ],
                        ]);

                        RunGenerationJob::dispatch($run->id);
                    }),
                Action::make('runs')
                    ->label('История')
                    ->icon('heroicon-o-clock')
                    ->url(fn () => GenerationRunResource::getUrl('index')),
                EditAction::make()
                    ->label('Настройки')
                    ->url(fn (GenerationTemplate $record) => GenerationTemplateResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}

