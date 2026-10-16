<?php

declare(strict_types=1);

namespace App\Services\Proxy;

use App\Contracts\ProxyClientInterface;
use App\DTO\ProxyCredentials;
use App\Exceptions\ProxyCredentialsException;
use App\Support\ComponentLog;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GolangProxyClient implements ProxyClientInterface
{
    public function getCredentials(): ProxyCredentials
    {
        try {
            $response = Http::timeout(10)->get($this->goUrl('/internal/proxy/credentials'));
        } catch (Throwable $exception) {
            ComponentLog::error('GolangProxyClient', 'Go proxy unreachable', [
                'error' => $exception->getMessage(),
            ]);

            throw new ProxyCredentialsException('Unable to get proxy credentials.');
        }

        if (! $response->successful()) {
            ComponentLog::error('GolangProxyClient', 'Go proxy rejected the request', [
                'status' => $response->status(),
            ]);

            throw new ProxyCredentialsException('Unable to get proxy credentials.');
        }

        $url = $response->json('url');
        $userAgent = $response->json('user_agent');

        if (! is_string($url) || $url === '' || ! is_string($userAgent) || $userAgent === '') {
            throw new ProxyCredentialsException('Unable to get proxy credentials.');
        }

        ComponentLog::info('GolangProxyClient', 'received credentials');

        return new ProxyCredentials(url: $url, userAgent: $userAgent);
    }

    public function bench(string $url): void
    {
        try {
            $response = Http::timeout(10)->post($this->goUrl('/internal/proxy/bench'), [
                'url' => $url,
            ]);
        } catch (Throwable $exception) {
            ComponentLog::error('GolangProxyClient', 'unable to bench proxy', [
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            ComponentLog::error('GolangProxyClient', 'unable to bench proxy', [
                'status' => $response->status(),
            ]);
        }
    }

    private function goUrl(string $path): string
    {
        return rtrim((string) config('services.go_proxy.base_url'), '/').$path;
    }
}
