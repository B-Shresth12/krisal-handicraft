<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use JsonException;

class CatalogSeeder extends Seeder
{
    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $data = json_decode(
            File::get(database_path('seeders/data/catalog.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($data): void {
            $categories = $this->seedCategories($data['categories']);
            $collections = $this->seedCollections($data['collections']);

            $this->seedProducts($data['products'], $categories, $collections);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function seedCategories(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $category = Category::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    // etsy_count in catalog.json is reference data only.
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                ],
            );

            $ids[$row['slug']] = $category->id;
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function seedCollections(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $collection = Collection::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'image_path' => filled($row['image']) ? $this->copyImage($row['image']) : null,
                    'is_featured' => $row['is_featured'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                ],
            );

            $ids[$row['slug']] = $collection->id;
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $categories
     * @param  array<string, int>  $collections
     */
    private function seedProducts(array $rows, array $categories, array $collections): void
    {
        foreach ($rows as $row) {
            $product = Product::withTrashed()->updateOrCreate(
                ['public_id' => $row['public_id']],
                [
                    'slug' => $row['slug'],
                    'name' => $row['name'],
                    'short_description' => $row['short_description'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                    // Seed data only carries USD; NPR is derived until an
                    // admin enters an override.
                    'price_npr' => null,
                    'compare_at_price' => $row['compare_at_price'],
                    'compare_at_price_npr' => null,
                    'category_id' => $categories[$row['category_slug']],
                    'stock_quantity' => $row['stock_quantity'],
                    'rating' => $row['rating'],
                    'review_count' => $row['review_count'],
                    'etsy_listing_id' => $row['etsy_listing_id'],
                    'etsy_url' => $row['etsy_url'],
                    'is_bestseller' => $row['is_bestseller'],
                    'is_new_arrival' => $row['is_new_arrival'],
                    'is_active' => $row['is_active'],
                    'sort_order' => $row['sort_order'],
                ],
            );

            $product->collections()->sync(
                array_map(fn (string $slug): int => $collections[$slug], $row['collection_slugs']),
            );

            $this->syncImages($product, $row['images']);
            $this->syncColors($product, $row['colors']);
            $this->syncSizes($product, $row['sizes']);
            $this->syncSpecifications($product, $row['specifications']);
        }
    }

    /**
     * @param  array<int, string>  $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $paths = [];

        foreach ($images as $sortOrder => $filename) {
            $path = $this->copyImage($filename);

            $paths[] = $path;

            $product->images()->updateOrCreate(
                ['path' => $path],
                ['alt_text' => null, 'sort_order' => $sortOrder],
            );
        }

        $this->deleteMissing($product->images(), 'path', $paths);
    }

    /**
     * @param  array<int, array{name: string, hex: string}>  $colors
     */
    private function syncColors(Product $product, array $colors): void
    {
        $names = [];

        foreach ($colors as $sortOrder => $color) {
            $names[] = $color['name'];

            $product->colors()->updateOrCreate(
                ['name' => $color['name']],
                ['hex' => $color['hex'], 'sort_order' => $sortOrder],
            );
        }

        $this->deleteMissing($product->colors(), 'name', $names);
    }

    /**
     * @param  array<int, string>  $sizes
     */
    private function syncSizes(Product $product, array $sizes): void
    {
        $labels = [];

        foreach ($sizes as $sortOrder => $label) {
            $labels[] = $label;

            $product->sizes()->updateOrCreate(
                ['label' => $label],
                ['sort_order' => $sortOrder],
            );
        }

        $this->deleteMissing($product->sizes(), 'label', $labels);
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $specifications
     */
    private function syncSpecifications(Product $product, array $specifications): void
    {
        $labels = [];

        foreach ($specifications as $sortOrder => $specification) {
            $labels[] = $specification['label'];

            $product->specifications()->updateOrCreate(
                ['label' => $specification['label']],
                ['value' => $specification['value'], 'sort_order' => $sortOrder],
            );
        }

        $this->deleteMissing($product->specifications(), 'label', $labels);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function deleteMissing(HasMany $relation, string $column, array $values): void
    {
        if ($values === []) {
            $relation->delete();

            return;
        }

        $relation->whereNotIn($column, $values)->delete();
    }

    /**
     * Store each shared image once and return its public disk path.
     */
    private function copyImage(string $filename): string
    {
        $path = 'catalog/'.$filename;

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put(
                $path,
                File::get(database_path('seeders/data/images/'.$filename)),
            );
        }

        return $path;
    }
}
