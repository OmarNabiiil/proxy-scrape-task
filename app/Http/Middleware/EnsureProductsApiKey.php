<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ComponentLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureProductsApiKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.products.api_key');
        $provided = (string) $request->header('X-API-Key', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            ComponentLog::warning('EnsureProductsApiKey', 'rejected list request');

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
