<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->actingAs($this->admin);

        $this->category = Category::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function productState(array $overrides = []): array
    {
        return [
            'name' => 'Test Singing Bowl',
            'slug' => 'test-singing-bowl',
            'public_id' => '1122334455',
            'category_id' => $this->category->id,
            'price' => 100,
            'price_npr' => null,
            'compare_at_price' => null,
            'compare_at_price_npr' => null,
            'stock_quantity' => 5,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_usd_price_is_required(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->productState(['price' => null]))
            ->call('create')
            ->assertHasFormErrors(['price']);
    }

    public function test_compare_at_price_must_be_greater_than_price_in_usd(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->productState([
                'price' => 100,
                'compare_at_price' => 90,
            ]))
            ->call('create')
            ->assertHasFormErrors(['compare_at_price']);
    }

    public function test_compare_at_price_must_be_greater_than_price_in_npr(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->productState([
                'price' => 100,
                'price_npr' => 13000,
                'compare_at_price' => 120,
                'compare_at_price_npr' => 12500,
            ]))
            ->call('create')
            ->assertHasFormErrors(['compare_at_price_npr']);
    }

    public function test_npr_override_must_be_greater_than_zero(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->productState(['price_npr' => 0]))
            ->call('create')
            ->assertHasFormErrors(['price_npr']);
    }

    public function test_valid_dual_currency_product_is_saved(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm($this->productState([
                'price' => 100,
                'price_npr' => 14000,
                'compare_at_price' => 120,
                'compare_at_price_npr' => 16500,
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('public_id', '1122334455')->firstOrFail();
        $product->load('category');

        $this->assertSame('100.00', $product->price);
        $this->assertSame('14000.00', $product->price_npr);
        $this->assertSame(14000, $product->effective_price_npr);
        $this->assertSame(16500, $product->effective_compare_at_price_npr);
        $this->assertTrue($product->category->is($this->category));
    }

    public function test_stock_status_filter_uses_the_configured_threshold(): void
    {
        config(['shop.low_stock_threshold' => 2]);

        $out = Product::factory()->for($this->category)->create(['stock_quantity' => 0]);
        $low = Product::factory()->for($this->category)->create(['stock_quantity' => 2]);
        $in = Product::factory()->for($this->category)->create(['stock_quantity' => 3]);

        Livewire::test(ListProducts::class)
            ->filterTable('stock_status', 'in_stock')
            ->assertCanSeeTableRecords([$in])
            ->assertCanNotSeeTableRecords([$out, $low]);

        Livewire::test(ListProducts::class)
            ->filterTable('stock_status', 'low_stock')
            ->assertCanSeeTableRecords([$low])
            ->assertCanNotSeeTableRecords([$out, $in]);

        Livewire::test(ListProducts::class)
            ->filterTable('stock_status', 'out_of_stock')
            ->assertCanSeeTableRecords([$out])
            ->assertCanNotSeeTableRecords([$low, $in]);
    }

    public function test_reordering_the_products_table_persists_sort_order(): void
    {
        $first = Product::factory()->for($this->category)->create(['sort_order' => 1]);
        $second = Product::factory()->for($this->category)->create(['sort_order' => 2]);

        Livewire::test(ListProducts::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_navigation_badge_counts_low_and_out_of_stock_products(): void
    {
        config(['shop.low_stock_threshold' => 2]);

        Product::factory()->for($this->category)->create(['stock_quantity' => 0]);
        Product::factory()->for($this->category)->create(['stock_quantity' => 1]);
        Product::factory()->for($this->category)->create(['stock_quantity' => 2]);
        Product::factory()->for($this->category)->create(['stock_quantity' => 3]);
        Product::factory()->for($this->category)->create(['stock_quantity' => 5]);

        $this->assertSame('3', ProductResource::getNavigationBadge());
        $this->assertSame('warning', ProductResource::getNavigationBadgeColor());
    }

    public function test_catalog_seeder_creates_expected_counts_and_leaves_npr_null(): void
    {
        Storage::fake('public');

        // The shared test category would otherwise be counted alongside the
        // eight categories the seeder creates.
        $this->category->delete();

        $this->seed(CatalogSeeder::class);

        $threshold = (int) config('shop.low_stock_threshold', 2);

        $this->assertSame(8, Category::query()->count());
        $this->assertSame(17, Collection::query()->count());
        $this->assertSame(41, Product::query()->count());

        $this->assertSame(38, Product::query()->where('stock_quantity', '>', $threshold)->count());
        $this->assertSame(3, Product::query()
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', $threshold)
            ->count());
        $this->assertSame(0, Product::query()->where('stock_quantity', '<=', 0)->count());

        $this->assertSame(0, Product::query()->whereNotNull('price_npr')->count());
        $this->assertSame(0, Product::query()->whereNotNull('compare_at_price_npr')->count());
    }
}
