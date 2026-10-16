<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ScrapeProductData;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_list_requires_api_key(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();

        $this->getJson('/api/products', ['X-API-Key' => 'wrong'])->assertUnauthorized();

        $this->getJson('/api/products', ['X-API-Key' => 'test-products-key'])
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_list_shape_and_search(): void
    {
        Product::factory()->create([
            'title' => 'Wireless Keyboard',
            'price' => 19.99,
            'image_url' => 'https://example.com/k.jpg',
        ]);
        Product::factory()->create(['title' => 'Desk Lamp']);

        $response = $this->getJson('/api/products', ['X-API-Key' => 'test-products-key']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'price', 'image_url', 'created_at'],
                ],
            ]);

        $this->getJson('/api/products?search=keyboard', ['X-API-Key' => 'test-products-key'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Wireless Keyboard');
    }

    public function test_list_does_not_dispatch_scrape_jobs(): void
    {
        Bus::fake();

        $this->getJson('/api/products', ['X-API-Key' => 'test-products-key'])->assertOk();

        Bus::assertNotDispatched(ScrapeProductData::class);
    }

    public function test_scheduled_command_dispatches_one_job_per_url(): void
    {
        Bus::fake();

        $this->artisan('scrape:scheduled')->assertSuccessful();

        Bus::assertDispatchedTimes(ScrapeProductData::class, 3);
    }
}
