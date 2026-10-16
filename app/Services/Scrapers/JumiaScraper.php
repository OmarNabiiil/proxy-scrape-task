<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Contracts\ScraperStrategyInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Live Jumia PDP selectors captured 2026-09-15 from
 * https://www.jumia.com.ng/fashion-seamless-shapewear-bodysuit-womens-tummy-control-body-shapers-309486537.html
 *
 * title: h1.-fs20.-ptm.-pbxs
 * price: span.-b.-ubpt.-tal.-fs24.-prxs (₦ 5,500 on that PDP)
 * image: #imgs img[data-src] (src is a 1×1 placeholder)
 */
final class JumiaScraper implements ScraperStrategyInterface
{
    public const TITLE_SELECTOR = 'h1.-fs20.-ptm.-pbxs';

    public const PRICE_SELECTOR = 'span.-b.-ubpt.-tal.-fs24.-prxs';

    public const IMAGE_SELECTOR = '#imgs img';

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

            $imageUrl = $this->extractor->extractAttribute($crawler, self::IMAGE_SELECTOR, 'data-src');
            if ($imageUrl === '') {
                $imageUrl = $this->extractor->extractAttribute($crawler, '#imgs a.itm', 'href');
            }

            return [
                'title' => $this->extractor->extractRequiredText($crawler, self::TITLE_SELECTOR),
                'price' => $this->extractor->extractPrice($crawler, self::PRICE_SELECTOR),
                'image_url' => $imageUrl,
            ];
        });
    }
}
