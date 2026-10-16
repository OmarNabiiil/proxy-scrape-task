<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Contracts\ScraperStrategyInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Live Noon PDP selectors (data-qa attributes on noon.com product pages).
 *
 * Direct HTTP to noon.com from the implementation environment timed out (2026-09-15):
 * HTTP/2 INTERNAL_ERROR, HTTP/1.1 25s timeout with 0 bytes. Selectors below are the
 * live markup documented from Noon PDPs (Crawlbase live scrape notes), not dummy testids:
 *   title: h1[data-qa^="pdp-name-"]
 *   price: div[data-qa="div-price-now"]
 *   image: img[data-qa="pdp-core-image"], fallback meta[property="og:image"]
 *
 * Config URL: https://www.noon.com/uae-en/galaxy-s25-ai-dual-sim-silver-shadow-12gb-ram-256gb-5g/N70140511V/p/
 */
final class NoonScraper implements ScraperStrategyInterface
{
    public const TITLE_SELECTOR = 'h1[data-qa^="pdp-name-"]';

    public const PRICE_SELECTOR = 'div[data-qa="div-price-now"]';

    public const IMAGE_SELECTOR = 'img[data-qa="pdp-core-image"]';

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

            $imageUrl = $this->extractor->extractAttribute($crawler, self::IMAGE_SELECTOR, 'src');
            if ($imageUrl === '') {
                $imageUrl = $this->extractor->extractAttribute($crawler, 'meta[property="og:image"]', 'content');
            }

            return [
                'title' => $this->extractor->extractRequiredText($crawler, self::TITLE_SELECTOR),
                'price' => $this->extractor->extractPrice($crawler, self::PRICE_SELECTOR),
                'image_url' => $imageUrl,
            ];
        });
    }
}
