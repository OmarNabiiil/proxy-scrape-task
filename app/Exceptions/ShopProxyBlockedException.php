<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The shop returned HTTP 429 or 403 for this proxy. The fetcher benches that
 * proxy and retries on a different IP. The message stays generic so Laravel
 * does not treat it as a lost Redis/MySQL connection.
 */
final class ShopProxyBlockedException extends RuntimeException
{
    public static function forStatus(int $status): self
    {
        return new self('Shop blocked the request.');
    }
}
