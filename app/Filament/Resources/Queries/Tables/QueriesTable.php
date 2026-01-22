<?php

namespace App\Filament\Resources\Queries\Tables;

use App\Filament\Resources\Queries\QueryResource;
use App\Models\QueryGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QueriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction(null)
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
            ])
            ->columns([
                Panel::make([
                    Stack::make([
                        TextColumn::make('title')
                            ->label('')
                            ->weight('bold')
                            ->size('lg'),

                        TextColumn::make('topics_pending')
                            ->label('')
                            ->state(function (QueryGroup $record) {
                                return $record->topics()->where('status', 'pending')->count() . ' тем в очереди';
                            })
                            ->color('gray'),

                        TextColumn::make('next_topic')
                            ->label('')
                            ->state(function (QueryGroup $record) {
                                return $record->topics()
                                    ->where('status', 'pending')
                                    ->orderBy('position')
                                    ->value('topic');
                            })
                            ->placeholder('Очередь пуста')
                            ->limit(120)
                            ->extraAttributes(['class' => 'text-gray-600']),

                    ]),
                ])
            ])
            ->recordActions([
                DeleteAction::make(),
                Action::make('manage')
                    ->label('Открыть')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (QueryGroup $record) => QueryResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
