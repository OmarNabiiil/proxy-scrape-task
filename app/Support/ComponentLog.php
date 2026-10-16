<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class ComponentLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $component, string $message, array $context = []): void
    {
        Log::info('['.$component.'] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $component, string $message, array $context = []): void
    {
        Log::warning('['.$component.'] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $component, string $message, array $context = []): void
    {
        Log::error('['.$component.'] '.$message, $context);
    }
}
