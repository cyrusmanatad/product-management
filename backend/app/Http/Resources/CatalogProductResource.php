<?php

namespace App\Http\Resources;

use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductResource extends JsonResource
{
    /**
     * Public storefront shape. Stock is what a shopper can buy.
     * Reserved quantity stays on the staff product resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableStock = $this->variants->sum(function ($variant) {
            $onHand = optional($variant->inventory)->stock_quantity ?? 0;
            $reserved = optional($variant->inventory)->reserved_quantity ?? 0;

            return max(0, $onHand - $reserved);
        });

        $firstVariant = $this->variants->first();
        $variants = $this->variants->map(function ($variant) {
            $onHand = optional($variant->inventory)->stock_quantity ?? 0;
            $reserved = optional($variant->inventory)->reserved_quantity ?? 0;

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'attributes' => $variant->attributes ?? (object) [],
                'stock' => max(0, $onHand - $reserved),
            ];
        });

        return [
            'id' => $this->id,
            'currency' => app(TenantContext::class)->vendor->currency,
            'base_sku' => $this->base_sku ?? '',
            'title' => $this->title,
            'description' => $this->description,
            'variants' => $variants,
            'images' => ProductImageResource::collection($this->images),
            'primary_image_url' => $this->images->first()?->publicUrl(),
            'category_id' => $this->category->id ?? 0,
            'category' => $this->category?->name ?? 'Uncategorized',
            'price' => $firstVariant?->price ?? 0,
            'price_humanize' => number_format((float) ($firstVariant?->price ?? 0), 2),
            'sale_price' => $firstVariant?->sale_price ?? 0,
            'sp_humanize' => number_format((float) ($firstVariant?->sale_price ?? 0), 2),
            'slug' => $this->slug,
            'stock' => $availableStock,
            'stock_humanize' => number_format($availableStock),
            'uom' => $firstVariant?->uom ?? '',
            'status_label' => $availableStock > 0 ? 'Published' : 'Out Stock',
            'status' => $this->status,
            'createdAt' => $this->humanize_datetime,
        ];
    }
}
