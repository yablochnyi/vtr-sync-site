<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use BackedEnum;

class AiSettings extends Page implements HasSchemas
{
    use InteractsWithForms;
    protected string $view = 'filament.pages.ai-settings';
    protected static ?string $navigationLabel = 'AI настройки';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;
    protected static ?int $navigationSort = 9999;
    protected static bool $shouldRegisterNavigation = false;
    public static ?string $title = 'AI настройки';

    public ?array $data = [];

    public function mount(): void
    {
        $data = \App\Models\AiSettings::first();

        if ($data) {
            $this->form->fill($data->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('settings.url')
                    ->label('API URL')
                    ->required()
                    ->helperText('URL API совместимого с OpenAI'),
                TextInput::make('settings.key')
                    ->label('API KEY')
                    ->required()
                    ->password()
                    ->helperText('Ваш API ключ для доступа к сервису'),
                Select::make('settings.model')
                    ->label('Model')
                    ->required()
                    ->searchable()
                    ->options([
                        'gpt-4o-mini' => 'gpt-4o-mini',
                        'gpt-4o' => 'gpt-4o',
                        'gpt-4.1-mini' => 'gpt-4.1-mini',
                        'gpt-4.1' => 'gpt-4.1',
                        'gpt-4.1-nano' => 'gpt-4.1-nano',
                        'o3-mini' => 'o3-mini',
                    ])
                    ->helperText('Выберите модель.'),
                TextInput::make('settings.max_tokens')
                    ->label('Max tokens')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20000)
                    ->default(4000)
                    ->helperText('Ограничение длины ответа.'),
            ])
            ->statePath('data');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            '<div class="block sm:max-w-3xl
                     rounded-lg px-3 py-2 leading-relaxed
                     bg-gray-50 text-gray-700 border border-gray-200 shadow-sm
                     dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700
                     whitespace-normal break-words text-pretty fi-header-subheading mt-2 max-w-2xl text-lg text-gray-600 dark:text-gray-400 bg-white">
            Настройте API ключи и модели для генерации контента с помощью AI
        </div>'
        );
    }

    public function create(): void
    {
        $data = $this->form->getState();
        $record = \App\Models\AiSettings::first();
        if ($record) {
            $record->update($data);
        } else {
            \App\Models\AiSettings::create($data);
        }
        Notification::make()
            ->title('Данные сохранены')
            ->success()
            ->send();
    }
}
