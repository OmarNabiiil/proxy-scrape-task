<?php

declare(strict_types=1);

namespace App\Contracts;

interface ScraperStrategyInterface
{
    /**
     * @return array{title: string, price: float, image_url: string}
     */
    public function scrape(string $url): array;
}
