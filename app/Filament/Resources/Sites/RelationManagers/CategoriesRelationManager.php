<?php

namespace App\Filament\Resources\Sites\RelationManagers;

use App\Services\CustomApiService;
use App\Services\WordPressApiService;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Категории');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->label('Название')
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->label('URL')
                    ->maxLength(255),
                RichEditor::make('description')
                    ->required()
                    ->label('Описание')
                    ->columnSpanFull()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                ->label(''),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(function ($record, RelationManager $livewire) {

                        $site = $livewire->getOwnerRecord();

                        if (($site->type_api ?? null) === 'custom') {
                            $api = new CustomApiService(
                                url: $site->url,
                                login: $site->login,
                                password: $site->api_key
                            );

                            $remote = $api->createCategory(
                                name: $record->name,
                                slug: $record->slug,
                                description: $record->description,
                            );

                            $record->update([
                                'wp_id' => $remote['id'] ?? null,
                            ]);
                        } else {
                            $wp = new WordPressApiService(
                                $site->url,
                                $site->login,
                                $site->api_key
                            );

                            $wpData = $wp->createCategory(
                                name: $record->name,
                                slug: $record->slug,
                                description: $record->description,
                            );

                            $record->update([
                                'wp_id' => $wpData['id'],
                            ]);
                        }
                    })
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function ($record, array $data, RelationManager $livewire) {

                        $site = $livewire->getOwnerRecord();

                        if (!$record->wp_id) {
                            return;
                        }

                        if (($site->type_api ?? null) === 'custom') {
                            $api = new CustomApiService(
                                url: $site->url,
                                login: $site->login,
                                password: $site->api_key
                            );
                            $api->updateCategory(
                                id: (int) $record->wp_id,
                                name: $data['name'],
                                slug: $data['slug'],
                                description: $data['description'],
                            );
                        } else {
                            $wp = new WordPressApiService(
                                $site->url,
                                $site->login,
                                $site->api_key
                            );

                            $wp->updateCategory(
                                wpId: $record->wp_id,
                                name: $data['name'],
                                slug: $data['slug'],
                                description: $data['description'],
                            );
                        }
                    })
                    ->hiddenLabel(),

            ])
            ->toolbarActions([
                BulkActionGroup::make([

                ]),
            ]);
    }
}
