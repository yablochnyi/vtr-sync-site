<?php

namespace App\Filament\Resources\Queries\RelationManagers;

use App\Models\QueryTopic;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TopicsRelationManager extends RelationManager
{
    protected static string $relationship = 'topics';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Темы');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('topic')
                ->label('Тема')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('position')
                ->label('Позиция')
                ->numeric()
                ->helperText('Меньше = раньше в очереди')
                ->required(),
            Select::make('status')
                ->label('Статус')
                ->required()
                ->options([
                    'pending' => 'pending',
                    'reserved' => 'reserved',
                    'used' => 'used',
                    'failed' => 'failed',
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('topic')
                    ->label('Тема')
                    ->limit(120)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('used_at')
                    ->label('Использовано')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('bulkAdd')
                    ->label('Добавить списком')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Textarea::make('topics')
                            ->label('Темы (каждая с новой строки)')
                            ->required()
                            ->rows(10),
                    ])
                    ->action(function (array $data): void {
                        $raw = (string) ($data['topics'] ?? '');
                        $lines = preg_split("/\r\n|\r|\n/", $raw);
                        $lines = array_values(array_filter(array_map(static fn ($l) => trim((string) $l), $lines)));

                        if ($lines === []) {
                            return;
                        }

                        $owner = $this->getOwnerRecord();
                        $startPos = (int) $owner->topics()->max('position');
                        $startPos = $startPos < 0 ? 0 : ($startPos + 1);

                        foreach ($lines as $i => $line) {
                            QueryTopic::create([
                                'query_group_id' => $owner->id,
                                'topic' => $line,
                                'position' => $startPos + $i,
                                'status' => 'pending',
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function (QueryTopic $record, array $data) {
                        if (($data['status'] ?? null) === 'used' && $record->used_at === null) {
                            $record->update(['used_at' => now()]);
                        }
                        if (($data['status'] ?? null) !== 'used' && $record->used_at !== null) {
                            $record->update(['used_at' => null]);
                        }
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

