<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\ProductRepositoryInterface;
use App\DTO\ScrapedProductData;
use Illuminate\Database\Seeder;

/**
 * Demo rows so the catalog page can be checked without waiting for a scrape.
 * Run on demand: php artisan db:seed --class=ProductSeeder
 */
final class ProductSeeder extends Seeder
{
    public function run(ProductRepositoryInterface $repository): void
    {
        // The last row has no image on purpose, so the card placeholder is easy to check.
        $products = [
            ['Dell Alienware Horizon Gaming Backpack AW423P', 4599.00, 'https://picsum.photos/seed/backpack/800/800'],
            ['Samsung Galaxy S25 AI Dual SIM Silver Shadow 12GB RAM 256GB 5G', 3199.00, 'https://picsum.photos/seed/phone/800/800'],
            ['Seamless Shapewear Bodysuit Tummy Control', 5500.00, ''],
        ];

        foreach ($products as [$title, $price, $imageUrl]) {
            $repository->createOrUpdate(new ScrapedProductData(
                title: $title,
                price: $price,
                image_url: $imageUrl,
            ));
        }
    }
}
