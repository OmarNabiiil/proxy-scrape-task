<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Contracts\ScraperStrategyInterface;
use Symfony\Component\DomCrawler\Crawler;

final class AmazonScraper implements ScraperStrategyInterface
{
    public const TITLE_SELECTOR = '#productTitle';

    public const PRICE_SELECTOR = '.a-price .a-offscreen';

    public const IMAGE_SELECTOR = '#landingImage';

    public function __construct(
        private readonly ProductPageFetcher $fetcher,
        private readonly DomProductExtractor $extractor,
    ) {}

    /**
     * @return array{title: string, price: float, image_url: string}
     */
    public function scrape(string $url): array
    {
        return $this->fetcher->withProxyHops($url, function (string $html): array {
            $crawler = new Crawler($html);

            return [
                'title' => $this->extractor->extractRequiredText($crawler, self::TITLE_SELECTOR),
                'price' => $this->extractor->extractPrice($crawler, self::PRICE_SELECTOR),
                'image_url' => $this->extractor->extractAttribute($crawler, self::IMAGE_SELECTOR, 'src'),
            ];
        });
    }
}
