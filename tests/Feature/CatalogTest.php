<?php

namespace Tests\Feature;

use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\ProductSpecification;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_relationships_are_wired(): void
    {
        $category = Category::factory()->create();
        $collection = Collection::factory()->create();
        $product = Product::factory()->for($category)->create();

        $product->collections()->attach($collection);
        $product->images()->create(['path' => 'catalog/test.jpg', 'sort_order' => 0]);
        $product->colors()->create(['name' => 'Bronze', 'hex' => '#A87C4F', 'sort_order' => 0]);
        $product->sizes()->create(['label' => 'Medium', 'sort_order' => 0]);
        $product->specifications()->create(['label' => 'Origin', 'value' => 'Nepal', 'sort_order' => 0]);

        $this->assertTrue($product->category->is($category));
        $this->assertTrue($category->products->contains($product));
        $this->assertTrue($product->collections->contains($collection));
        $this->assertTrue($collection->products->contains($product));
        $this->assertCount(1, $product->images);
        $this->assertCount(1, $product->colors);
        $this->assertCount(1, $product->sizes);
        $this->assertCount(1, $product->specifications);
        $this->assertTrue($product->images->first()->product->is($product));
    }

    public function test_catalog_seeder_imports_the_storefront_catalog(): void
    {
        Storage::fake('public');

        $this->seed(CatalogSeeder::class);

        $this->assertSame(8, Category::count());
        $this->assertSame(17, Collection::count());
        $this->assertSame(41, Product::count());
        $this->assertSame(6, Collection::where('is_featured', true)->count());
        $this->assertDatabaseCount('collection_product', 73);
        $this->assertSame(69, ProductImage::count());
        $this->assertSame(42, ProductColor::count());
        $this->assertSame(63, ProductSize::count());
        $this->assertSame(147, ProductSpecification::count());
        $this->assertSame(0, Product::doesntHave('collections')->count());

        $products = Product::with('images')->get();

        $this->assertSame(38, $products->filter(fn (Product $product): bool => $product->stock_status === StockStatus::InStock)->count());
        $this->assertSame(3, $products->filter(fn (Product $product): bool => $product->stock_status === StockStatus::LowStock)->count());
        $this->assertSame(0, $products->filter(fn (Product $product): bool => $product->stock_status === StockStatus::OutOfStock)->count());

        $this->assertCount(6, Storage::disk('public')->files('catalog'));
        Storage::disk('public')->assertExists('catalog/chakra_bowl_set_1794811479723.jpg');
        $this->assertSame('catalog/chakra_bowl_set_1794811479723.jpg', $products->firstWhere('slug', 'full-moon-chakra-singing-bowl-set-tuned-cdefgab')->images->first()->path);
    }

    public function test_catalog_seeder_can_be_run_twice(): void
    {
        Storage::fake('public');

        $this->seed(CatalogSeeder::class);

        $counts = [
            Category::count(),
            Collection::count(),
            Product::count(),
            ProductImage::count(),
            ProductColor::count(),
            ProductSize::count(),
            ProductSpecification::count(),
            DB::table('collection_product')->count(),
        ];

        $this->seed(CatalogSeeder::class);

        $this->assertSame($counts, [
            Category::count(),
            Collection::count(),
            Product::count(),
            ProductImage::count(),
            ProductColor::count(),
            ProductSize::count(),
            ProductSpecification::count(),
            DB::table('collection_product')->count(),
        ]);
        $this->assertCount(6, Storage::disk('public')->files('catalog'));
    }

    public function test_stock_status_is_derived_from_the_quantity(): void
    {
        config(['shop.low_stock_threshold' => 2]);

        $outOfStock = Product::factory()->create(['stock_quantity' => 0]);
        $lowStock = Product::factory()->create(['stock_quantity' => 2]);
        $inStock = Product::factory()->create(['stock_quantity' => 3]);

        $this->assertSame(StockStatus::OutOfStock, $outOfStock->stock_status);
        $this->assertSame(StockStatus::LowStock, $lowStock->stock_status);
        $this->assertSame(StockStatus::InStock, $inStock->stock_status);
        $this->assertSame('out_of_stock', $outOfStock->stock_status->value);
        $this->assertSame('low_stock', $lowStock->stock_status->value);
        $this->assertSame('in_stock', $inStock->stock_status->value);

        config(['shop.low_stock_threshold' => 5]);

        $boundary = Product::factory()->create(['stock_quantity' => 5]);

        $this->assertSame(StockStatus::LowStock, $boundary->stock_status);
    }
}
