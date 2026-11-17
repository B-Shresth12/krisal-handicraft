<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Services\CurrencyConverter;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_id',
    'slug',
    'name',
    'short_description',
    'description',
    'price',
    'price_npr',
    'compare_at_price',
    'compare_at_price_npr',
    'category_id',
    'stock_quantity',
    'rating',
    'review_count',
    'etsy_listing_id',
    'etsy_url',
    'is_bestseller',
    'is_new_arrival',
    'is_active',
    'sort_order',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_npr' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'compare_at_price_npr' => 'decimal:2',
            'rating' => 'decimal:1',
            'stock_quantity' => 'integer',
            'review_count' => 'integer',
            'is_bestseller' => 'boolean',
            'is_new_arrival' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * The image shown in listings: the first one by sort order.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany([
            'sort_order' => 'min',
            'id' => 'min',
        ]);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    /**
     * NPR is an override; the effective amount is the override or the USD
     * amount converted at the configured rate.
     */
    protected function effectivePriceNpr(): Attribute
    {
        return Attribute::get(fn (): int => $this->hasPriceNprOverride()
            ? (int) round((float) $this->price_npr)
            : (app(CurrencyConverter::class)->usdToNpr($this->price) ?? 0));
    }

    /**
     * The effective NPR compare-at amount, or null when neither amount exists.
     */
    protected function effectiveCompareAtPriceNpr(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->hasCompareAtPriceNprOverride()) {
                return (int) round((float) $this->compare_at_price_npr);
            }

            if ($this->compare_at_price === null) {
                return null;
            }

            return app(CurrencyConverter::class)->usdToNpr($this->compare_at_price);
        });
    }

    public function hasPriceNprOverride(): bool
    {
        return $this->price_npr !== null;
    }

    public function hasCompareAtPriceNprOverride(): bool
    {
        return $this->compare_at_price_npr !== null;
    }

    /**
     * The stock status is derived from the quantity so it cannot drift.
     */
    protected function stockStatus(): Attribute
    {
        return Attribute::get(function (): StockStatus {
            $threshold = (int) config('shop.low_stock_threshold', 2);

            return match (true) {
                $this->stock_quantity <= 0 => StockStatus::OutOfStock,
                $this->stock_quantity <= $threshold => StockStatus::LowStock,
                default => StockStatus::InStock,
            };
        });
    }
}
