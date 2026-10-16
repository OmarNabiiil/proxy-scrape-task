<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ProductRepositoryInterface;
use App\Contracts\ProxyClientInterface;
use App\Repositories\MysqlProductRepository;
use App\Services\Proxy\GolangProxyClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, MysqlProductRepository::class);
        $this->app->bind(ProxyClientInterface::class, GolangProxyClient::class);
    }

    public function boot(): void
    {
        //
    }
}
