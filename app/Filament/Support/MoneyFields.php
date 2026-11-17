<?php

namespace App\Filament\Support;

use App\Enums\Currency;
use App\Services\CurrencyConverter;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class MoneyFields
{
    /**
     * Build a USD input and its optional NPR override side by side.
     *
     * @return array{0: TextInput, 1: TextInput}
     */
    public static function make(
        string $field,
        string $label,
        bool $required = false,
        ?string $compareTo = null,
    ): array {
        $nprField = "{$field}_npr";

        $usd = TextInput::make($field)
            ->label("{$label} (USD)")
            ->prefix(Currency::Usd->symbol())
            ->numeric()
            ->step(0.01)
            ->minValue(0)
            ->required($required)
            ->live(onBlur: true);

        $npr = TextInput::make($nprField)
            ->label("{$label} (NPR override)")
            ->prefix(Currency::Npr->symbol())
            ->numeric()
            ->step(0.01)
            ->minValue(0.01)
            ->placeholder(fn (Get $get): ?string => filled($get($field))
                ? number_format((int) app(CurrencyConverter::class)->usdToNpr($get($field)))
                : null)
            ->helperText(fn (): string => 'Leave blank to convert automatically at 1 USD = '
                .app(CurrencyConverter::class)->formattedRate().' NPR.');

        if ($compareTo === null) {
            return [$usd, $npr];
        }

        $usd->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $compareTo): void {
            if (blank($value) || blank($get($compareTo))) {
                return;
            }

            if ((float) $value <= (float) $get($compareTo)) {
                $fail('The compare-at price must be greater than the price.');
            }
        });

        $npr->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $compareTo): void {
            if (blank($get("{$compareTo}_npr")) && blank($get($nprField))) {
                return;
            }

            $converter = app(CurrencyConverter::class);

            $priceNpr = filled($get("{$compareTo}_npr"))
                ? (float) $get("{$compareTo}_npr")
                : $converter->usdToNpr($get($compareTo));

            $compareNpr = filled($value)
                ? (float) $value
                : $converter->usdToNpr($get($field));

            if ($priceNpr === null || $compareNpr === null || $compareNpr <= $priceNpr) {
                $fail('The compare-at price in NPR must be greater than the price in NPR.');
            }
        });

        return [$usd, $npr];
    }
}
