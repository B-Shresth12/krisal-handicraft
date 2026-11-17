<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Filament\Resources\Products\Actions\DuplicateProductAction;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('primaryImage.path')
                    ->label('')
                    ->disk('public')
                    ->imageSize(44)
                    ->square()
                    ->checkFileExistence(false),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price (USD)')
                    ->money(Currency::Usd->value)
                    ->sortable(),
                // The NPR price is computed from the USD amount and the setting,
                // so it cannot be sorted in SQL. The arrow icon marks derived values.
                TextColumn::make('effective_price_npr')
                    ->label('Price (NPR)')
                    ->formatStateUsing(fn (int|string|null $state): string => $state === null
                        ? '—'
                        : Currency::Npr->symbol().' '.number_format((int) $state))
                    ->icon(fn (Product $record): ?string => $record->hasPriceNprOverride() ? null : 'heroicon-m-arrow-path')
                    ->tooltip(fn (Product $record): ?string => $record->hasPriceNprOverride()
                        ? 'Manual NPR override'
                        : 'Auto-converted from USD')
                    ->color('gray'),
                TextInputColumn::make('stock_quantity')
                    ->label('Stock')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0'])
                    ->disabled(fn (Product $record): bool => $record->trashed())
                    ->sortable(),
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
                ToggleColumn::make('is_active')
                    ->label('Active'),
                ToggleColumn::make('is_bestseller')
                    ->label('Bestseller'),
                ToggleColumn::make('is_new_arrival')
                    ->label('New'),
                TextColumn::make('collections_count')
                    ->counts('collections')
                    ->label('Collections')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('public_id')
                    ->label('Public ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('review_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
                TernaryFilter::make('is_bestseller')
                    ->label('Bestseller'),
                TernaryFilter::make('is_new_arrival')
                    ->label('New arrival'),
                SelectFilter::make('stock_status')
                    ->label('Stock status')
                    ->options([
                        StockStatus::InStock->value => 'In stock',
                        StockStatus::LowStock->value => 'Low stock',
                        StockStatus::OutOfStock->value => 'Out of stock',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $threshold = (int) config('shop.low_stock_threshold', 2);
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return match ($value) {
                            StockStatus::InStock->value => $query->where('stock_quantity', '>', $threshold),
                            StockStatus::LowStock->value => $query
                                ->where('stock_quantity', '>', 0)
                                ->where('stock_quantity', '<=', $threshold),
                            StockStatus::OutOfStock->value => $query->where('stock_quantity', '<=', 0),
                            default => $query,
                        };
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DuplicateProductAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each(
                            fn (Product $record) => $record->update(['is_active' => true]),
                        ))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->action(fn (Collection $records) => $records->each(
                            fn (Product $record) => $record->update(['is_active' => false]),
                        ))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markBestseller')
                        ->label('Mark bestseller')
                        ->icon(Heroicon::OutlinedStar)
                        ->action(fn (Collection $records) => $records->each(
                            fn (Product $record) => $record->update(['is_bestseller' => true]),
                        ))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unmarkBestseller')
                        ->label('Unmark bestseller')
                        ->icon(Heroicon::OutlinedStar)
                        ->action(fn (Collection $records) => $records->each(
                            fn (Product $record) => $record->update(['is_bestseller' => false]),
                        ))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('changeCategory')
                        ->label('Change category')
                        ->icon(Heroicon::OutlinedFolderOpen)
                        ->schema([
                            Select::make('category_id')
                                ->label('Category')
                                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each(
                            fn (Product $record) => $record->update(['category_id' => $data['category_id']]),
                        ))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
