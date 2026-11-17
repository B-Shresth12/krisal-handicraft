<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use App\Services\CurrencyConverter;
use Illuminate\Database\Seeder;

class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        StoreSetting::query()->firstOrCreate([], [
            'usd_to_npr_rate' => StoreSetting::DEFAULT_USD_TO_NPR_RATE,
        ]);

        // DatabaseSeeder runs with model events disabled, so clear the cached
        // rate explicitly to be safe.
        app(CurrencyConverter::class)->forgetRate();
    }
}
