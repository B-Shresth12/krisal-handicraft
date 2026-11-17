<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StoreSettings;
use App\Models\Product;
use App\Services\CurrencyConverter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $threshold = (int) config('shop.low_stock_threshold', 2);

        return [
            Stat::make('Active products', Product::query()->where('is_active', true)->count())
                ->description('Visible in the catalog')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            Stat::make('Low stock', Product::query()
                ->where('stock_quantity', '>', 0)
                ->where('stock_quantity', '<=', $threshold)
                ->count())
                ->description("1 to {$threshold} left")
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingDown)
                ->color('warning'),
            Stat::make('Out of stock', Product::query()->where('stock_quantity', '<=', 0)->count())
                ->description('Needs restocking')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color('danger'),
            Stat::make('USD to NPR rate', '1 USD = '.app(CurrencyConverter::class)->formattedRate().' NPR')
                ->description('Manage store settings')
                ->descriptionIcon(Heroicon::OutlinedCog6Tooth)
                ->url(StoreSettings::getUrl())
                ->color('info'),
        ];
    }
}
