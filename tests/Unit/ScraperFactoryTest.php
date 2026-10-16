<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Factories\ScraperFactory;
use App\Services\Scrapers\AmazonScraper;
use App\Services\Scrapers\JumiaScraper;
use App\Services\Scrapers\NoonScraper;
use InvalidArgumentException;
use Tests\TestCase;

final class ScraperFactoryTest extends TestCase
{
    public function test_resolves_amazon_jumia_and_noon(): void
    {
        $factory = new ScraperFactory;

        $this->assertInstanceOf(AmazonScraper::class, $factory->make('https://www.amazon.com/dp/B0D1XD1ZV3'));
        $this->assertInstanceOf(JumiaScraper::class, $factory->make('https://www.jumia.com.ng/item.html'));
        $this->assertInstanceOf(NoonScraper::class, $factory->make('https://www.noon.com/uae-en/item/p/'));
    }

    public function test_invalid_url_and_unsupported_host_throw(): void
    {
        $factory = new ScraperFactory;

        try {
            $factory->make('not-a-url');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid URL provided.', $e->getMessage());
        }

        try {
            $factory->make('https://example.com/p');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('No scraper strategy supported for: example.com', $e->getMessage());
        }
    }
}
