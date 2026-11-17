<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;

class CurrencyConverter
{
    private const CACHE_KEY = 'store.usd_to_npr_rate';

    /**
     * The configured USD to NPR rate, cached until the setting is saved.
     */
    public function rate(): float
    {
        return (float) Cache::rememberForever(
            self::CACHE_KEY,
            fn (): string => (string) StoreSetting::current()->usd_to_npr_rate,
        );
    }

    /**
     * The rate formatted without trailing zeroes, for admin helper text.
     */
    public function formattedRate(): string
    {
        return rtrim(rtrim(number_format($this->rate(), 4, '.', ''), '0'), '.');
    }

    /**
     * Convert USD to whole Nepalese rupees.
     *
     * bcmath is used when it is installed so the multiplication keeps its
     * precision before the final half-up rounding. When bcmath is missing we
     * fall back to PHP's round() with PHP_ROUND_HALF_UP.
     */
    public function usdToNpr(float|int|string|null $usd): ?int
    {
        $usd = $this->normalise($usd);

        if ($usd === null) {
            return null;
        }

        if ($this->supportsBcmath()) {
            // bcadd(..., '0.5', 0) adds a half and truncates at zero decimals,
            // which is half-up rounding for the positive amounts used here.
            return (int) bcadd(bcmul($usd, $this->rateString(), 6), '0.5', 0);
        }

        return (int) round((float) $usd * $this->rate(), 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Convert Nepalese rupees to USD, rounded to two decimals.
     */
    public function nprToUsd(float|int|string|null $npr): ?float
    {
        $npr = $this->normalise($npr);

        if ($npr === null) {
            return null;
        }

        if ($this->supportsBcmath()) {
            return (float) bcadd(bcdiv($npr, $this->rateString(), 8), '0.005', 2);
        }

        return round((float) $npr / $this->rate(), 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Forget the cached rate. Called whenever the setting is saved.
     */
    public function forgetRate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function normalise(float|int|string|null $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }

    private function rateString(): string
    {
        return number_format($this->rate(), 4, '.', '');
    }

    private function supportsBcmath(): bool
    {
        return function_exists('bcmul') && function_exists('bcadd') && function_exists('bcdiv');
    }
}
