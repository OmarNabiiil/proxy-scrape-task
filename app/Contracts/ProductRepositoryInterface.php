<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\ScrapedProductData;
use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function createOrUpdate(ScrapedProductData $data): Product;

    /**
     * @return Collection<int, Product>
     */
    public function all(?string $search = null): Collection;
}
