<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Contracts\ScraperStrategyInterface;
use App\Services\Scrapers\AmazonScraper;
use App\Services\Scrapers\JumiaScraper;
use App\Services\Scrapers\NoonScraper;
use App\Support\ComponentLog;
use InvalidArgumentException;

final class ScraperFactory
{
    public function make(string $url): ScraperStrategyInterface
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Invalid URL provided.');
        }

        $host = strtolower($host);

        $scraper = match (true) {
            str_contains($host, 'amazon') => app(AmazonScraper::class),
            str_contains($host, 'jumia.com') => app(JumiaScraper::class),
            str_contains($host, 'noon.com') => app(NoonScraper::class),
            default => throw new InvalidArgumentException("No scraper strategy supported for: {$host}"),
        };

        ComponentLog::info('ScraperFactory', 'chose scraper', [
            'host' => $host,
            'scraper' => $scraper::class,
        ]);

        return $scraper;
    }
}
