<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ProductRepositoryInterface;
use App\DTO\ScrapedProductData;
use App\Exceptions\ProxyCredentialsException;
use App\Services\Factories\ScraperFactory;
use App\Support\ComponentLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

final class ScrapeProductData implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $targetUrl,
    ) {}

    public function handle(ScraperFactory $factory, ProductRepositoryInterface $repository): void
    {
        Redis::throttle('global-scraping-limit')
            ->allow(20)
            ->every(60)
            ->block(10)
            ->then(function () use ($factory, $repository): void {
                try {
                    ComponentLog::info('ScrapeProductData', 'scraping', ['url' => $this->targetUrl]);

                    $scraper = $factory->make($this->targetUrl);
                    $productData = $scraper->scrape($this->targetUrl);

                    $product = $repository->createOrUpdate(new ScrapedProductData(
                        title: $productData['title'],
                        price: $productData['price'],
                        image_url: $productData['image_url'],
                    ));

                    ComponentLog::info('ScrapeProductData', 'saved product', [
                        'url' => $this->targetUrl,
                        'id' => $product->id,
                        'title' => $product->title,
                    ]);
                } catch (ProxyCredentialsException) {
                    ComponentLog::warning('ScrapeProductData', 'no proxy available, releasing', [
                        'url' => $this->targetUrl,
                    ]);
                    $this->release((int) config('scraping.proxy_empty_release_seconds', 60));
                }
            }, function (): void {
                ComponentLog::warning('ScrapeProductData', 'throttled, releasing for 30s', [
                    'url' => $this->targetUrl,
                ]);
                $this->release(30);
            });
    }
}
