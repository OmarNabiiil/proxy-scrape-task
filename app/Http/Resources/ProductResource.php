<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, price: float, image_url: string, created_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => (float) $this->price,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
