<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\ProxyCredentials;
use App\Exceptions\ProductPageFetchException;
use App\Exceptions\ProxyCredentialsException;
use App\Exceptions\ShopProxyBlockedException;
use App\Services\Scrapers\AmazonScraper;
use App\Services\Scrapers\DomProductExtractor;
use App\Services\Scrapers\ProductPageFetcher;
use Illuminate\Database\LostConnectionDetector;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeProxyClient;
use Tests\TestCase;

final class ProductPageFetcherTest extends TestCase
{
    public function test_sends_proxy_option_and_user_agent(): void
    {
        Http::fake([
            'https://example.com/p' => Http::response('<html></html>', 200),
        ]);

        $html = (new ProductPageFetcher(new FakeProxyClient))->getHtml('https://example.com/p');

        $this->assertSame('<html></html>', $html);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://example.com/p'
                && $request->hasHeader('User-Agent', 'RotateBot/1.0');
        });
    }

    public function test_production_forces_tls_verify_on(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $fetcher = new ProductPageFetcher(new FakeProxyClient);

        $this->assertTrue($fetcher->shouldVerifyTls());
    }

    public function test_does_not_fetch_the_product_url_when_go_is_down(): void
    {
        Http::fake([
            'http://127.0.0.1:8081/*' => Http::failedConnection('Connection refused'),
            '*' => Http::response('<html>should-not-run</html>', 200),
        ]);

        try {
            app(ProductPageFetcher::class)->getHtml('https://www.amazon.com/dp/B0D1XD1ZV3');
            $this->fail('Expected proxy credentials to fail');
        } catch (ProxyCredentialsException) {
            // expected
        }

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'amazon.com'));
    }

    public function test_proxy_reset_does_not_look_like_a_lost_database_connection(): void
    {
        Http::fake([
            '*' => Http::failedConnection('cURL error 56: Recv failure: Connection reset by peer'),
        ]);

        $proxy = new FakeProxyClient;

        try {
            (new ProductPageFetcher($proxy))->getHtml('https://www.jumia.com.eg/item.html');
            $this->fail('Expected the page fetch to fail');
        } catch (ProductPageFetchException $exception) {
            $this->assertStringNotContainsString('reset by peer', $exception->getMessage());
            $this->assertFalse((new LostConnectionDetector)->causedByLostConnection($exception));
            $this->assertSame([], $proxy->benchedUrls);
        }
    }

    public function test_http_429_is_a_bench_flag(): void
    {
        Http::fake([
            '*' => Http::response('rate limited', 429),
        ]);

        $proxy = new FakeProxyClient;

        try {
            (new ProductPageFetcher($proxy))->getHtml('https://www.amazon.eg/item');
            $this->fail('Expected the page fetch to fail after hops');
        } catch (ShopProxyBlockedException) {
            $this->assertNotSame([], $proxy->benchedUrls);
        }
    }

    public function test_http_500_is_not_a_bench_flag(): void
    {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        $proxy = new FakeProxyClient;

        try {
            (new ProductPageFetcher($proxy))->getHtml('https://www.amazon.eg/item');
            $this->fail('Expected the page fetch to fail');
        } catch (ProductPageFetchException) {
            $this->assertSame([], $proxy->benchedUrls);
        }
    }

    public function test_http_401_and_404_are_not_bench_flags(): void
    {
        foreach ([401, 404] as $status) {
            Http::fake([
                '*' => Http::response('nope', $status),
            ]);

            $proxy = new FakeProxyClient;

            try {
                (new ProductPageFetcher($proxy))->getHtml('https://www.amazon.eg/item');
                $this->fail("Expected the page fetch to fail for HTTP {$status}");
            } catch (ProductPageFetchException) {
                $this->assertSame([], $proxy->benchedUrls);
            }
        }
    }

    public function test_http_429_stops_after_exactly_five_shop_gets(): void
    {
        $this->assertSame(5, (int) config('scraping.proxy_max_retries'));

        Http::fake([
            '*' => Http::sequence()
                ->push('rate limited', 429)
                ->push('rate limited', 429)
                ->push('rate limited', 429)
                ->push('rate limited', 429)
                ->push('rate limited', 429)
                ->push('rate limited', 429),
        ]);

        $proxy = new FakeProxyClient;

        try {
            (new ProductPageFetcher($proxy))->getHtml('https://www.amazon.eg/item');
            $this->fail('Expected the page fetch to fail after hops');
        } catch (ShopProxyBlockedException) {
            $shopGets = Http::recorded()
                ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'amazon.eg'))
                ->count();

            $this->assertSame(5, $shopGets);
        }
    }

    public function test_bench_post_failure_still_hops_to_the_next_proxy(): void
    {
        $logs = [];
        Log::listen(function (MessageLogged $event) use (&$logs): void {
            $logs[] = $event->message.' '.json_encode($event->context);
        });

        $credentials = 0;
        $shops = 0;

        Http::fake(function ($request) use (&$credentials, &$shops) {
            if ($request->url() === 'http://127.0.0.1:8081/internal/proxy/credentials') {
                $credentials++;

                if ($credentials === 1) {
                    return Http::response([
                        'url' => 'http://user:secret@proxy-a.example:1',
                        'user_agent' => 'UA-A',
                    ], 200);
                }

                return Http::response([
                    'url' => 'http://user:secret@proxy-b.example:2',
                    'user_agent' => 'UA-B',
                ], 200);
            }

            if ($request->url() === 'http://127.0.0.1:8081/internal/proxy/bench') {
                return Http::response(['message' => 'Unable to bench proxy'], 500);
            }

            $shops++;

            if ($shops === 1) {
                return Http::response('rate limited', 429);
            }

            return Http::response('<html>ok</html>', 200);
        });

        $html = app(ProductPageFetcher::class)->getHtml('https://www.amazon.eg/item');

        $this->assertSame('<html>ok</html>', $html);
        $this->assertSame(2, $shops);

        foreach ($logs as $entry) {
            $this->assertStringNotContainsString('user:secret', $entry);
        }
    }

    public function test_http_429_benches_the_used_proxy_and_retries_the_next(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('rate limited', 429)
                ->push('<html>ok</html>', 200),
        ]);

        $proxy = $this->twoProxies();

        $html = (new ProductPageFetcher($proxy))->getHtml('https://www.amazon.eg/item');

        $this->assertSame('<html>ok</html>', $html);
        $this->assertSame(['http://proxy-a.example:1'], $proxy->benchedUrls);

        $userAgents = Http::recorded()
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'amazon.eg'))
            ->map(fn (array $pair): string => $pair[0]->header('User-Agent')[0] ?? '')
            ->values()
            ->all();

        $this->assertSame(['UA-A', 'UA-B'], $userAgents);
    }

    public function test_http_403_benches_and_retries_like_429(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('forbidden', 403)
                ->push('<html>ok</html>', 200),
        ]);

        $proxy = $this->twoProxies();

        $html = (new ProductPageFetcher($proxy))->getHtml('https://www.amazon.eg/item');

        $this->assertSame('<html>ok</html>', $html);
        $this->assertSame(['http://proxy-a.example:1'], $proxy->benchedUrls);
    }

    public function test_missing_title_benches_the_used_proxy_and_retries(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('<html><body><p>continue shopping</p></body></html>', 200)
                ->push(
                    '<html><body>'
                    .'<span id="productTitle">Gaming Backpack</span>'
                    .'<div class="a-price"><span class="a-offscreen">$10.00</span></div>'
                    .'<img id="landingImage" src="https://img.example.com/a.jpg">'
                    .'</body></html>',
                    200,
                ),
        ]);

        $proxy = $this->twoProxies();

        $scraped = (new AmazonScraper(new ProductPageFetcher($proxy), new DomProductExtractor))
            ->scrape('https://www.amazon.eg/item');

        $this->assertSame('Gaming Backpack', $scraped['title']);
        $this->assertSame(['http://proxy-a.example:1'], $proxy->benchedUrls);
    }

    public function test_benches_the_credentials_url_that_was_just_used(): void
    {
        $credentials = 0;
        $shops = 0;

        Http::fake(function ($request) use (&$credentials, &$shops) {
            if ($request->url() === 'http://127.0.0.1:8081/internal/proxy/credentials') {
                $credentials++;

                if ($credentials === 1) {
                    return Http::response([
                        'url' => 'http://proxy-a.example:1',
                        'user_agent' => 'UA-A',
                    ], 200);
                }

                return Http::response([
                    'url' => 'http://proxy-b.example:2',
                    'user_agent' => 'UA-B',
                ], 200);
            }

            if ($request->url() === 'http://127.0.0.1:8081/internal/proxy/bench') {
                return Http::response(['message' => 'Proxy benched'], 200);
            }

            $shops++;

            if ($shops === 1) {
                return Http::response('rate limited', 429);
            }

            return Http::response('<html>ok</html>', 200);
        });

        $html = app(ProductPageFetcher::class)->getHtml('https://www.amazon.eg/item');

        $this->assertSame('<html>ok</html>', $html);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:8081/internal/proxy/bench'
                && $request['url'] === 'http://proxy-a.example:1';
        });
        Http::assertNotSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:8081/internal/proxy/bench'
                && $request['url'] === 'http://proxy-b.example:2';
        });
    }

    private function twoProxies(): FakeProxyClient
    {
        return FakeProxyClient::sequence(
            new ProxyCredentials(url: 'http://proxy-a.example:1', userAgent: 'UA-A'),
            new ProxyCredentials(url: 'http://proxy-b.example:2', userAgent: 'UA-B'),
        );
    }
}
