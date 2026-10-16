<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\ScrapedProductData;
use App\Models\Product;
use App\Repositories\MysqlProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MysqlProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_upserts_by_title_and_refreshes_created_at(): void
    {
        $repository = new MysqlProductRepository;

        Carbon::setTestNow('2026-09-16 12:00:00');

        $first = $repository->createOrUpdate(new ScrapedProductData(
            title: 'Same Title',
            price: 10.00,
            image_url: 'https://example.com/a.jpg',
        ));

        Carbon::setTestNow('2026-09-16 12:01:00');

        $second = $repository->createOrUpdate(new ScrapedProductData(
            title: 'Same Title',
            price: 12.50,
            image_url: 'https://example.com/b.jpg',
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(12.5, $second->price);
        $this->assertSame('https://example.com/b.jpg', $second->image_url);
        $this->assertNotSame(
            $first->created_at?->toIso8601String(),
            $second->created_at?->toIso8601String(),
        );
        $this->assertTrue($second->created_at?->equalTo(Carbon::parse('2026-09-16 12:01:00')));

        Carbon::setTestNow();
    }

    public function test_search_filters_titles(): void
    {
        Product::factory()->create(['title' => 'Wireless Mouse']);
        Product::factory()->create(['title' => 'USB Cable']);

        $repository = new MysqlProductRepository;
        $found = $repository->all('wireless');

        $this->assertCount(1, $found);
        $this->assertSame('Wireless Mouse', $found->first()?->title);
    }
}
