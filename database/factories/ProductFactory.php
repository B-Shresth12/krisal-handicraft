<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 20, 500);

        return [
            'public_id' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'name' => Str::title($name),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price' => $price,
            'price_npr' => null,
            'compare_at_price' => fake()->boolean(60) ? round($price * 1.2, 2) : null,
            'compare_at_price_npr' => null,
            'category_id' => Category::factory(),
            'stock_quantity' => fake()->numberBetween(0, 20),
            'rating' => fake()->randomFloat(1, 3, 5),
            'review_count' => fake()->numberBetween(0, 50),
            'etsy_listing_id' => null,
            'etsy_url' => null,
            'is_bestseller' => fake()->boolean(20),
            'is_new_arrival' => fake()->boolean(20),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock_quantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (): array => ['stock_quantity' => 2]);
    }
}
