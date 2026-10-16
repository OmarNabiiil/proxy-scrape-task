<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array{title: string, price: float, image_url: string}
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'price' => fake()->randomFloat(2, 1, 9999),
            'image_url' => fake()->boolean(80) ? 'https://example.com/'.fake()->uuid().'.jpg' : '',
        ];
    }
}
