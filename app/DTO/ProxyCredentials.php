<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ProxyCredentials
{
    public function __construct(
        public string $url,
        public string $userAgent,
    ) {}
}
