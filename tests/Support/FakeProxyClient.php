<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\ProxyClientInterface;
use App\DTO\ProxyCredentials;
use App\Exceptions\ProxyCredentialsException;

final class FakeProxyClient implements ProxyClientInterface
{
    /** @var list<string> */
    public array $benchedUrls = [];

    private int $index = 0;

    /**
     * @param  list<ProxyCredentials>  $credentials
     */
    public function __construct(
        private array $credentials = [],
        private readonly bool $repeatLast = true,
    ) {
        if ($this->credentials === [] && $this->repeatLast) {
            $this->credentials = [
                new ProxyCredentials(url: 'http://127.0.0.1:8888', userAgent: 'RotateBot/1.0'),
            ];
        }
    }

    public static function empty(): self
    {
        return new self(credentials: [], repeatLast: false);
    }

    public static function sequence(ProxyCredentials ...$credentials): self
    {
        return new self(credentials: array_values($credentials), repeatLast: false);
    }

    public function getCredentials(): ProxyCredentials
    {
        if ($this->credentials === []) {
            throw new ProxyCredentialsException('Unable to get proxy credentials.');
        }

        if ($this->index >= count($this->credentials)) {
            if (! $this->repeatLast) {
                throw new ProxyCredentialsException('Unable to get proxy credentials.');
            }

            return $this->credentials[array_key_last($this->credentials)];
        }

        $credentials = $this->credentials[$this->index];
        $this->index++;

        return $credentials;
    }

    public function bench(string $url): void
    {
        $this->benchedUrls[] = $url;
    }
}
