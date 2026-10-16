<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The shop page could not be downloaded (timeout, dead proxy, HTTP error).
 * The message stays generic so Laravel does not treat it as a lost Redis/MySQL
 * connection and stop the queue worker.
 */
final class ProductPageFetchException extends RuntimeException
{
    public static function forUrl(string $url, ?Throwable $previous = null): self
    {
        return new self("Failed to fetch HTML from target URL: {$url}.", 0, $previous);
    }
}
