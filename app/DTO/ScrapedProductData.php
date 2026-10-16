<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ScrapedProductData
{
    public function __construct(
        public string $title,
        public float $price,
        public string $image_url,
    ) {}
}
