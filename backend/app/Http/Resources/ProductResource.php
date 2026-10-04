<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate stock across all variants
        $totalStock = $this->variants->sum(function ($variant) {
            return optional($variant->inventory)->stock_quantity ?? 0;
        });

        $totalReserved = $this->variants->sum(function ($variant) {
            return optional($variant->inventory)->reserved_quantity ?? 0;
        });

        $availableStock = $totalStock - $totalReserved;

        // Get price from first variant
        $firstVariant = $this->variants->first();
        $variants = $this->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'attributes' => $variant->attributes,
                'stock' => $variant->inventory->stock_quantity ?? 0,
                'reserved_quantity' => $variant->inventory->reserved_quantity ?? 0,
            ];
        });

        return [
            'id' => $this->id,
            'base_sku' => $this->base_sku ?? '',
            'title' => $this->title,
            'description' => $this->description,
            'variants' => $variants,
            'images' => ProductImageResource::collection($this->images),
            'primary_image_url' => $this->images->first()?->publicUrl(),
            'category_id' => $this->category->id ?? 0,
            'category' => $this->category?->name ?? 'Uncategorized',
            'price' => $firstVariant?->price ?? 0,
            'price_humanize' => number_format($firstVariant?->price, 2) ?? 0,
            'sale_price' => $firstVariant?->sale_price ?? 0,
            'sp_humanize' => number_format($firstVariant?->sale_price, 2) ?? 0,
            'slug' => $this->slug,
            'stock' => $availableStock,
            'stock_humanize' => number_format($availableStock),
            'uom' => $firstVariant?->uom ?? '',
            'status_label' => $this->resolveStatus($availableStock),
            'status' => $this->status,
            'createdAt' => $this->humanize_datetime,
        ];
    }

    private function resolveStatus(int $stock): string
    {
        return match (true) {
            $this->status === 'published' && $stock > 0 => 'Published',
            $this->status === 'published' && $stock <= 0 => 'Out Stock',
            $this->status === 'draft' => 'Draft List',
            $this->status === 'out-of-stock' => 'Out Stock',
            default => 'Inactive',
        };
    }
}
