<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ProductRepositoryInterface;
use App\DTO\ScrapedProductData;
use App\Models\Product;
use App\Support\ComponentLog;
use Illuminate\Support\Collection;

final class MysqlProductRepository implements ProductRepositoryInterface
{
    public function createOrUpdate(ScrapedProductData $data): Product
    {
        $product = Product::query()->where('title', $data->title)->first();

        if ($product === null) {
            $created = Product::query()->create([
                'title' => $data->title,
                'price' => $data->price,
                'image_url' => $data->image_url,
            ]);

            ComponentLog::info('MysqlProductRepository', 'created product', [
                'id' => $created->id,
                'title' => $created->title,
            ]);

            return $created;
        }

        $product->price = $data->price;
        $product->image_url = $data->image_url;
        $product->created_at = now();
        $product->save();

        ComponentLog::info('MysqlProductRepository', 'updated product', [
            'id' => $product->id,
            'title' => $product->title,
        ]);

        return $product;
    }

    public function all(?string $search = null): Collection
    {
        return Product::query()
            ->when(
                $search !== null && $search !== '',
                function ($query) use ($search): void {
                    $escaped = addcslashes($search, '%_\\');
                    $query->where('title', 'like', '%'.$escaped.'%');
                },
            )
            ->orderByDesc('id')
            ->get();
    }
}
