<?php

declare(strict_types=1);

namespace App\Services\Scrapers;

use App\Contracts\ProxyClientInterface;
use App\DTO\ProxyCredentials;
use App\Exceptions\ProductNotOnPageException;
use App\Exceptions\ProductPageFetchException;
use App\Exceptions\ShopProxyBlockedException;
use App\Support\ComponentLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ProductPageFetcher
{
    public function __construct(
        private readonly ProxyClientInterface $proxyClient,
    ) {}

    public function getHtml(string $url): string
    {
        return $this->withProxyHops($url, fn (string $html): string => $html);
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $onHtml
     * @return T
     */
    public function withProxyHops(string $url, callable $onHtml): mixed
    {
        $max = max(1, (int) config('scraping.proxy_max_retries', 5));
        $last = null;

        for ($hop = 1; $hop <= $max; $hop++) {
            $credentials = $this->proxyClient->getCredentials();

            try {
                return $onHtml($this->fetchOnce($url, $credentials));
            } catch (ShopProxyBlockedException|ProductNotOnPageException $exception) {
                $this->safeBench($credentials->url);
                $last = $exception;

                continue;
            }
        }

        throw $last ?? ProductPageFetchException::forUrl($url);
    }

    public function shouldVerifyTls(): bool
    {
        if (app()->environment('production')) {
            return true;
        }

        return (bool) config('scraping.tls_verify', true);
    }

    private function fetchOnce(string $url, ProxyCredentials $credentials): string
    {
        ComponentLog::info('ProductPageFetcher', 'fetching page', ['url' => $url]);

        try {
            $response = Http::withOptions([
                'proxy' => $credentials->url,
                'verify' => $this->shouldVerifyTls(),
            ])->connectTimeout((int) config('scraping.connect_timeout', 5))
                ->timeout((int) config('scraping.timeout', 20))
                ->withHeaders([
                    'User-Agent' => $credentials->userAgent,
                ])->get($url);
        } catch (ConnectionException $exception) {
            ComponentLog::error('ProductPageFetcher', 'page fetch failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            throw ProductPageFetchException::forUrl($url, $exception);
        }

        $status = $response->status();

        if (! $response->successful()) {
            ComponentLog::error('ProductPageFetcher', 'page fetch failed', [
                'url' => $url,
                'status' => $status,
            ]);

            if ($status === 429 || $status === 403) {
                throw ShopProxyBlockedException::forStatus($status);
            }

            throw ProductPageFetchException::forUrl($url);
        }

        return $response->body();
    }

    private function safeBench(string $proxyUrl): void
    {
        try {
            $this->proxyClient->bench($proxyUrl);
        } catch (Throwable) {
            ComponentLog::error('ProductPageFetcher', 'unable to bench proxy');
        }
    }
}
