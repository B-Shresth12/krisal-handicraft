<?php

namespace App\Enums;

enum Currency: string
{
    case Usd = 'USD';
    case Npr = 'NPR';

    public function label(): string
    {
        return match ($this) {
            self::Usd => 'US Dollar',
            self::Npr => 'Nepalese Rupee',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Usd => '$',
            self::Npr => 'Rs.',
        };
    }

    public function decimalPlaces(): int
    {
        return match ($this) {
            self::Usd => 2,
            // The storefront displays NPR as whole rupees.
            self::Npr => 0,
        };
    }
}
