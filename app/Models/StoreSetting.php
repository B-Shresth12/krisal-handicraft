<?php

namespace App\Models;

use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['usd_to_npr_rate'])]
class StoreSetting extends Model
{
    /**
     * The single rate row is created with this value the first time settings
     * are read. It is the only place the initial 135 default lives.
     */
    public const DEFAULT_USD_TO_NPR_RATE = 135;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usd_to_npr_rate' => 'decimal:4',
        ];
    }

    /**
     * The settings are stored as a single row so there is no key lookup.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'usd_to_npr_rate' => self::DEFAULT_USD_TO_NPR_RATE,
        ]);
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            app(CurrencyConverter::class)->forgetRate();
        });
    }
}
