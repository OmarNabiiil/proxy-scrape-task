<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\ProxyClientInterface;
use App\DTO\ProxyCredentials;
use App\DTO\ScrapedProductData;
use App\Jobs\ScrapeProductData;
use App\Models\Product;
use App\Services\Factories\ScraperFactory;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Limiters\DurationLimiterBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\FakeProxyClient;
use Tests\TestCase;

final class ScrapeProductDataJobTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET_URL = 'https://www.amazon.eg/-/en/item/dp/B0CPVPWHYR';

    protected function setUp(): void
    {
        parent::setUp();

        // The Go pool itself is covered by GolangProxyClientTest; here we only need credentials.
        $this->app->bind(ProxyClientInterface::class, fn (): ProxyClientInterface => new FakeProxyClient);
    }

    public function test_same_title_twice_keeps_one_row_and_refreshes_created_at(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push($this->shopHtml(price: '10.00', imageUrl: 'https://img.example.com/a.jpg'))
                ->push($this->shopHtml(price: '12.50', imageUrl: 'https://img.example.com/b.jpg')),
        ]);

        Carbon::setTestNow('2026-09-16 12:00:00');
        $this->fakeThrottle(allowed: true);
        $this->runJob();

        $first = Product::query()->sole();

        Carbon::setTestNow('2026-09-16 12:01:00');
        $this->fakeThrottle(allowed: true);
        $this->runJob();

        $second = Product::query()->sole();

        $this->assertSame(1, Product::query()->count());
        $this->assertSame($first->id, $second->id);
        $this->assertSame(12.5, $second->price);
        $this->assertSame('https://img.example.com/b.jpg', $second->image_url);
        $this->assertNotSame(
            $first->created_at?->toIso8601String(),
            $second->created_at?->toIso8601String(),
        );
        $this->assertTrue($second->created_at?->equalTo(Carbon::parse('2026-09-16 12:01:00')));

        Carbon::setTestNow();
    }

    public function test_repository_receives_the_scraped_dto(): void
    {
        Http::fake(['*' => Http::response($this->shopHtml(price: '10.00', imageUrl: 'https://img.example.com/a.jpg'))]);
        $this->fakeThrottle(allowed: true);

        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldReceive('createOrUpdate')
            ->once()
            ->withArgs(fn (ScrapedProductData $data): bool => $data->title === 'Gaming Backpack'
                && $data->price === 10.0
                && $data->image_url === 'https://img.example.com/a.jpg')
            ->andReturn(new Product);

        (new ScrapeProductData(self::TARGET_URL))->handle(app(ScraperFactory::class), $repository);
    }

    public function test_releases_back_to_the_queue_for_thirty_seconds_when_throttled(): void
    {
        Http::fake();
        $this->fakeThrottle(allowed: false);

        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldNotReceive('createOrUpdate');

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('release')->once()->with(30);

        $job = new ScrapeProductData(self::TARGET_URL);
        $job->setJob($queueJob);
        $job->handle(app(ScraperFactory::class), $repository);

        Http::assertNothingSent();
    }

    public function test_releases_for_sixty_seconds_when_the_proxy_pool_is_empty(): void
    {
        Http::fake();
        $this->app->bind(ProxyClientInterface::class, fn (): ProxyClientInterface => FakeProxyClient::empty());
        $this->fakeThrottle(allowed: true);

        $repository = Mockery::mock(ProductRepositoryInterface::class);
        $repository->shouldNotReceive('createOrUpdate');

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('release')->once()->with(60);

        $job = new ScrapeProductData(self::TARGET_URL);
        $job->setJob($queueJob);
        $job->handle(app(ScraperFactory::class), $repository);

        Http::assertNothingSent();
    }

    public function test_retries_a_429_on_a_second_proxy_and_saves_the_product(): void
    {
        $proxy = FakeProxyClient::sequence(
            new ProxyCredentials(url: 'http://proxy-a.example:1', userAgent: 'UA-A'),
            new ProxyCredentials(url: 'http://proxy-b.example:2', userAgent: 'UA-B'),
        );
        $this->app->bind(ProxyClientInterface::class, fn (): ProxyClientInterface => $proxy);

        Http::fake([
            '*' => Http::sequence()
                ->push('rate limited', 429)
                ->push($this->shopHtml(price: '10.00', imageUrl: 'https://img.example.com/a.jpg')),
        ]);
        $this->fakeThrottle(allowed: true);

        $this->runJob();

        $this->assertSame(1, Product::query()->count());
        $this->assertSame('Gaming Backpack', Product::query()->sole()->title);
        $this->assertSame(['http://proxy-a.example:1'], $proxy->benchedUrls);
    }

    private function runJob(): void
    {
        (new ScrapeProductData(self::TARGET_URL))
            ->handle(app(ScraperFactory::class), app(ProductRepositoryInterface::class));
    }

    private function shopHtml(string $price, string $imageUrl): string
    {
        return <<<HTML
            <html><body>
                <span id="productTitle">Gaming Backpack</span>
                <div class="a-price"><span class="a-offscreen">\${$price}</span></div>
                <img id="landingImage" src="{$imageUrl}">
            </body></html>
            HTML;
    }

    /**
     * Mocks the Redis duration limiter so the job's throttle callbacks run without a Redis server.
     */
    private function fakeThrottle(bool $allowed): void
    {
        /** @var DurationLimiterBuilder&MockInterface $builder */
        $builder = Mockery::mock(DurationLimiterBuilder::class);
        $builder->shouldReceive('allow')->once()->with(20)->andReturnSelf();
        $builder->shouldReceive('every')->once()->with(60)->andReturnSelf();
        $builder->shouldReceive('block')->once()->with(10)->andReturnSelf();
        $builder->shouldReceive('then')->once()->andReturnUsing(
            fn (callable $onAllowed, callable $onThrottled) => $allowed ? $onAllowed() : $onThrottled(),
        );

        Redis::shouldReceive('throttle')->once()->with('global-scraping-limit')->andReturn($builder);
    }
}
