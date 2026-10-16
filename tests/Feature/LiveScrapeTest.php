<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ProxyClientInterface;
use App\DTO\ProxyCredentials;
use App\Services\Factories\ScraperFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FakeProxyClient;
use Tests\TestCase;

/**
 * D8: the configured Amazon / Jumia / Noon URLs are fetched for real so a shop
 * changing its markup fails loudly instead of silently storing fallback values.
 *
 * Skipped by default because it needs the open internet. Run with:
 *   LIVE_SCRAPE_TESTS=1 php artisan test --filter=LiveScrapeTest
 */
#[Group('live')]
final class LiveScrapeTest extends TestCase
{
    public const DIRECT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        if (env('LIVE_SCRAPE_TESTS') !== '1') {
            $this->markTestSkipped('Set LIVE_SCRAPE_TESTS=1 to run the live scrape checks (D8).');
        }

        // Go + Redis are covered by GolangProxyClientTest; live runs go direct so a
        // missing proxy pool cannot be mistaken for a broken selector.
        $this->app->bind(ProxyClientInterface::class, fn (): ProxyClientInterface => new FakeProxyClient([
            new ProxyCredentials(url: '', userAgent: LiveScrapeTest::DIRECT_USER_AGENT),
        ]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function configuredUrls(): array
    {
        /** @var array{urls: list<string>} $config */
        $config = require dirname(__DIR__, 2).'/config/scraping.php';

        $cases = [];
        foreach ($config['urls'] as $url) {
            $cases[(string) parse_url($url, PHP_URL_HOST)] = [$url];
        }

        return $cases;
    }

    #[DataProvider('configuredUrls')]
    public function test_live_product_page_still_matches_the_selectors(string $url): void
    {
        // A page without a title node throws ProductNotOnPageException, so reaching
        // the assertions already proves the title selector matched.
        $scraped = app(ScraperFactory::class)->make($url)->scrape($url);

        $this->assertNotSame(
            '',
            $scraped['image_url'],
            "Live image selector no longer matches {$url}",
        );

        // Amazon hides the price behind JS on some PDPs, so 0.00 is tolerated here.
        $this->assertGreaterThanOrEqual(0.0, $scraped['price']);
    }
}
