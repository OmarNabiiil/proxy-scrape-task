<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ProductRepositoryInterface;
use App\Http\Requests\IndexProductsRequest;
use App\Http\Resources\ProductResource;
use App\Support\ComponentLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(IndexProductsRequest $request): AnonymousResourceCollection
    {
        $search = $request->validated('search');
        $rows = $this->products->all(is_string($search) ? $search : null);

        ComponentLog::info('ProductController', 'listed products', [
            'count' => $rows->count(),
            'search' => is_string($search) ? $search : '',
        ]);

        return PostResource::collection($rows);
    }
}
