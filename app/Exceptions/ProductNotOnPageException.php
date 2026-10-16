<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The response was readable but held no product: usually a bot check or
 * "continue shopping" interstitial served to the proxy we used. The job fails
 * so the next attempt rotates to another proxy instead of storing empty values.
 */
final class ProductNotOnPageException extends RuntimeException
{
    public static function forSelector(string $selector): self
    {
        return new self("No product found on the page (selector: {$selector}).");
    }
}
