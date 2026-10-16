<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\ProxyCredentials;

interface ProxyClientInterface
{
    public function getCredentials(): ProxyCredentials;

    public function bench(string $url): void;
}
