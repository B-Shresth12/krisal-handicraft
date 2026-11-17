<?php

namespace App\Filament\Widgets;

use App\Enums\StockStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockProducts extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low stock products';

    public function table(Table $table): Table
    {
        $threshold = (int) config('shop.low_stock_threshold', 2);

        return $table
            ->query(
                Product::query()
                    ->with('category')
                    ->where('stock_quantity', '<=', $threshold)
                    ->orderBy('stock_quantity')
                    ->orderBy('name')
                    ->limit(5),
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->color('primary')
                    ->weight('medium'),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextInputColumn::make('stock_quantity')
                    ->label('Stock')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0']),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Product $record): string => $record->stock_status->value)
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        StockStatus::InStock->value => 'success',
                        StockStatus::LowStock->value => 'warning',
                        StockStatus::OutOfStock->value => 'danger',
                    }),
            ]);
    }
}
