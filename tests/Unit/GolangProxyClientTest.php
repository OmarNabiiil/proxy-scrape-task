<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\ProxyCredentials;
use App\Exceptions\ProxyCredentialsException;
use App\Services\Proxy\GolangProxyClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GolangProxyClientTest extends TestCase
{
    public function test_maps_successful_json_to_credentials(): void
    {
        Http::fake([
            'http://127.0.0.1:8081/internal/proxy/credentials' => Http::response([
                'url' => 'http://127.0.0.1:8888',
                'user_agent' => 'TestAgent/1.0',
            ], 200),
        ]);

        $credentials = (new GolangProxyClient)->getCredentials();

        $this->assertInstanceOf(ProxyCredentials::class, $credentials);
        $this->assertSame('http://127.0.0.1:8888', $credentials->url);
        $this->assertSame('TestAgent/1.0', $credentials->userAgent);
    }

    public function test_non_successful_response_throws(): void
    {
        Http::fake([
            'http://127.0.0.1:8081/internal/proxy/credentials' => Http::response([
                'message' => 'No proxy available',
            ], 503),
        ]);

        $this->expectException(ProxyCredentialsException::class);

        (new GolangProxyClient)->getCredentials();
    }

    public function test_connection_failure_throws_without_leaking_proxy_url(): void
    {
        Http::fake([
            'http://127.0.0.1:8081/internal/proxy/credentials' => Http::failedConnection('Connection refused'),
        ]);

        $this->expectException(ProxyCredentialsException::class);
        $this->expectExceptionMessage('Unable to get proxy credentials.');

        (new GolangProxyClient)->getCredentials();
    }

    public function test_bench_posts_the_exact_url_and_does_not_log_it(): void
    {
        Http::fake([
            'http://127.0.0.1:8081/internal/proxy/bench' => Http::response([
                'message' => 'Proxy benched',
            ], 200),
        ]);

        (new GolangProxyClient)->bench('http://user:pass@203.0.113.10:8080');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:8081/internal/proxy/bench'
                && $request['url'] === 'http://user:pass@203.0.113.10:8080';
        });
    }
}
