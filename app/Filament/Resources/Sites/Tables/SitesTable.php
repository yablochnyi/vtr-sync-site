<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Filament\Pages\Posts;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\KataCompetitions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Panel::make([
                    Stack::make([

                        Split::make([
                            TextColumn::make('name')
                                ->weight('bold')
                                ->size('lg'),
                            TextColumn::make('type_api')
                                ->badge()
                                ->colors([
                                    'primary' => 'wordpress',
                                    'success' => 'custom',
                                ])
                                ->formatStateUsing(fn(string $state) => ucfirst($state)),
                        ]),
                        TextColumn::make('url')
                            ->color('gray')
                            ->copyable()
                            ->grow(),

                    ]),
                ])

            ])
            ->recordActions([
                DeleteAction::make()
                    ->color('danger')
                    ->icon('heroicon-o-trash'),
                EditAction::make('edit')
                    ->label('Управление')
                    ->icon('heroicon-o-pencil')
            ]);
    }
}
