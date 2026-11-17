<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_catalog_pages_load(): void
    {
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/collections')->assertOk();
        $this->get('/admin/products')->assertOk();

        $product = Product::factory()->create();

        $this->get("/admin/products/{$product->getRouteKey()}/edit")->assertOk();
    }

    public function test_category_can_be_created_through_the_resource(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Gongs',
                'slug' => 'gongs',
                'description' => 'Hand-hammered Himalayan gongs.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', [
            'slug' => 'gongs',
            'name' => 'Gongs',
            'is_active' => true,
        ]);
    }

    public function test_collection_can_be_created_with_products(): void
    {
        $product = Product::factory()->create();

        Livewire::test(CreateCollection::class)
            ->fillForm([
                'name' => 'Featured Gongs',
                'slug' => 'featured-gongs',
                'is_featured' => true,
                'is_active' => true,
                'products' => [$product->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $collection = Collection::where('slug', 'featured-gongs')->firstOrFail();
        $collection->load('products');

        $this->assertTrue($collection->is_featured);
        $this->assertTrue($collection->products->contains($product));
    }

    public function test_product_can_be_created_with_children_and_images(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $collection = Collection::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Antique Singing Bowl',
                'slug' => 'antique-singing-bowl',
                'public_id' => '1234567890',
                'category_id' => $category->id,
                'price' => 249.99,
                'compare_at_price' => 320,
                'stock_quantity' => 4,
                'is_active' => true,
                'collections' => [$collection->id],
                'images' => [
                    [
                        'path' => [UploadedFile::fake()->image('front.jpg')],
                        'alt_text' => 'Front',
                    ],
                    [
                        'path' => [UploadedFile::fake()->image('side.jpg')],
                        'alt_text' => 'Side',
                    ],
                ],
                'colors' => [
                    ['name' => 'Antique Bronze', 'hex' => '#A87C4F'],
                ],
                'sizes' => [
                    ['label' => 'Medium (7 inch)'],
                ],
                'specifications' => [
                    ['label' => 'Origin', 'value' => 'Kathmandu, Nepal'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('public_id', '1234567890')->firstOrFail();
        $product->load(['category', 'collections', 'images', 'colors', 'sizes', 'specifications']);

        $this->assertTrue($product->category->is($category));
        $this->assertTrue($product->collections->contains($collection));
        $this->assertCount(2, $product->images);
        $this->assertSame('Front', $product->images->firstWhere('alt_text', 'Front')->alt_text);
        $this->assertSame([1, 2], $product->images->pluck('sort_order')->sort()->values()->all());
        $this->assertCount(1, $product->colors);
        $this->assertCount(1, $product->sizes);
        $this->assertCount(1, $product->specifications);
        $this->assertNull($product->price_npr);
        $this->assertCount(2, Storage::disk('public')->files('catalog'));
    }

    public function test_repeater_rows_can_be_reordered_and_deleted(): void
    {
        $product = Product::factory()->create();

        $first = $product->colors()->create([
            'name' => 'Bronze',
            'hex' => '#A87C4F',
            'sort_order' => 1,
        ]);
        $second = $product->colors()->create([
            'name' => 'Gold',
            'hex' => '#D4AF37',
            'sort_order' => 2,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.colors', [
                "record-{$second->id}" => ['name' => 'Gold', 'hex' => '#D4AF37'],
                "record-{$first->id}" => ['name' => 'Bronze', 'hex' => '#A87C4F'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.colors', [
                "record-{$second->id}" => ['name' => 'Gold', 'hex' => '#D4AF37'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('product_colors', ['id' => $first->id]);
        $this->assertDatabaseHas('product_colors', ['id' => $second->id]);
    }

    public function test_product_can_be_duplicated_with_its_children(): void
    {
        $category = Category::factory()->create();
        $collection = Collection::factory()->create();

        $product = Product::factory()->for($category)->create([
            'name' => 'Moon Bowl',
            'slug' => 'moon-bowl',
            'public_id' => '9876543210',
            'is_active' => true,
        ]);

        $product->images()->create(['path' => 'catalog/moon.jpg', 'alt_text' => 'Moon', 'sort_order' => 1]);
        $product->colors()->create(['name' => 'Silver', 'hex' => '#C0C0C0', 'sort_order' => 1]);
        $product->sizes()->create(['label' => 'Large', 'sort_order' => 1]);
        $product->specifications()->create(['label' => 'Origin', 'value' => 'Nepal', 'sort_order' => 1]);
        $product->collections()->attach($collection);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('replicate')
            ->assertHasNoActionErrors();

        $copy = Product::where('name', 'Moon Bowl (Copy)')->firstOrFail();
        $copy->load(['images', 'colors', 'sizes', 'specifications', 'collections']);

        $this->assertFalse($copy->is_active);
        $this->assertNotSame($product->slug, $copy->slug);
        $this->assertNotSame($product->public_id, $copy->public_id);
        $this->assertCount(1, $copy->images);
        $this->assertCount(1, $copy->colors);
        $this->assertCount(1, $copy->sizes);
        $this->assertCount(1, $copy->specifications);
        $this->assertTrue($copy->collections->contains($collection));
    }

    public function test_etsy_count_columns_are_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('categories', 'etsy_count'));
        $this->assertFalse(Schema::hasColumn('collections', 'etsy_count'));
    }
}
