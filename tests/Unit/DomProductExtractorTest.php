<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ProductNotOnPageException;
use App\Services\Scrapers\DomProductExtractor;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

final class DomProductExtractorTest extends TestCase
{
    public function test_reads_text_price_and_attribute(): void
    {
        $crawler = new Crawler(<<<'HTML'
            <html><body>
                <span id="productTitle">  Gaming Backpack  </span>
                <div class="a-price"><span class="a-offscreen">EGP 4,599.00</span></div>
                <img id="landingImage" src=" https://img.example.com/a.jpg ">
            </body></html>
            HTML);

        $extractor = new DomProductExtractor;

        $this->assertSame('Gaming Backpack', $extractor->extractRequiredText($crawler, '#productTitle'));
        $this->assertSame(4599.00, $extractor->extractPrice($crawler, '.a-price .a-offscreen'));
        $this->assertSame('https://img.example.com/a.jpg', $extractor->extractAttribute($crawler, '#landingImage', 'src'));
    }

    public function test_skips_amazons_empty_price_span_and_reads_the_real_one(): void
    {
        // Live amazon.eg markup: the core price span is empty until JavaScript fills it.
        $crawler = new Crawler(<<<'HTML'
            <html><body>
                <div id="corePriceDisplay_desktop_feature_div"><span class="a-price"><span class="a-offscreen"></span></span></div>
                <div id="corePrice_feature_div"><span class="a-price"><span class="a-offscreen">EGP 1,945.00</span></span></div>
            </body></html>
            HTML);

        $this->assertSame(1945.00, (new DomProductExtractor)->extractPrice($crawler, '.a-price .a-offscreen'));
    }

    public function test_price_and_image_fall_back_to_empty_values(): void
    {
        $crawler = new Crawler('<html><body><span id="productTitle">Gaming Backpack</span></body></html>');
        $extractor = new DomProductExtractor;

        $this->assertSame(0.00, $extractor->extractPrice($crawler, '.a-price .a-offscreen'));
        $this->assertSame('', $extractor->extractAttribute($crawler, '#landingImage', 'src'));
    }

    public function test_a_bot_interstitial_is_rejected_instead_of_stored(): void
    {
        // What Amazon serves some proxies: HTTP 200, no product nodes.
        $crawler = new Crawler('<html><body><h4>Click the button below to continue shopping</h4></body></html>');

        $this->expectException(ProductNotOnPageException::class);

        (new DomProductExtractor)->extractRequiredText($crawler, '#productTitle');
    }

    public function test_an_empty_title_node_is_rejected(): void
    {
        $crawler = new Crawler('<html><body><span id="productTitle">   </span></body></html>');

        $this->expectException(ProductNotOnPageException::class);

        (new DomProductExtractor)->extractRequiredText($crawler, '#productTitle');
    }
}
