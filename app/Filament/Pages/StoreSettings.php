<?php

namespace App\Filament\Pages;

use App\Models\StoreSetting;
use App\Services\CurrencyConverter;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StoreSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Store settings';

    protected static ?string $slug = 'store-settings';

    protected string $view = 'filament.pages.store-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'usd_to_npr_rate' => (float) StoreSetting::current()->usd_to_npr_rate,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Currency')
                    ->description('USD is the source of truth. NPR amounts that are left blank on a product are converted with this rate.')
                    ->schema([
                        TextInput::make('usd_to_npr_rate')
                            ->label('1 USD = ? NPR')
                            ->helperText(fn (): string => 'Example: $100 = Rs. '.number_format(
                                (int) app(CurrencyConverter::class)->usdToNpr(100),
                            ).' NPR.')
                            ->required()
                            ->numeric()
                            ->step(0.0001)
                            ->minValue(0.0001),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        StoreSetting::current()->update([
            'usd_to_npr_rate' => $data['usd_to_npr_rate'],
        ]);

        Notification::make()
            ->success()
            ->title('Store settings saved')
            ->send();

        $this->form->fill([
            'usd_to_npr_rate' => (float) StoreSetting::current()->fresh()->usd_to_npr_rate,
        ]);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active;
    }
}
