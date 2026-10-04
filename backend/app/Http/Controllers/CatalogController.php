<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Http\Resources\CatalogProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Published products for the public shop.
     */
    private function catalog(): Builder
    {
        return Product::query()
            ->select(['id', 'category_id', 'base_sku', 'title', 'description', 'slug', 'status', 'created_at'])
            ->with([
                'images',
                'variants' => fn ($q) => $q->where('is_active', true)->where('currency', app(TenantContext::class)->vendor->currency)->select(['id', 'product_id', 'sku', 'uom', 'price', 'sale_price', 'currency', 'attributes']),
                'variants.inventory:id,variant_id,stock_quantity,reserved_quantity',
                'category:id,name',
            ])
            ->where('status', ProductStatus::PUBLISHED->value)
            ->orderByDesc('created_at');
    }

    public function show(string $slug, string $productSlug)
    {
        return new CatalogProductResource($this->catalog()->where('slug', $productSlug)
            ->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('currency', app(TenantContext::class)->vendor->currency))
            ->firstOrFail());
    }

    public function products(Request $request)
    {
        $query = $this->catalog();

        if ($request->filled('category')) {
            $categories = $request->category;
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('id', (array) $categories);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($q2) use ($search) {
                        $q2->where('sku', 'like', "%{$search}%")
                            ->orWhere('uom', 'like', "%{$search}%");
                    });
            });
        }

        return CatalogProductResource::collection($query->paginate(10));
    }

    /**
     * Categories that currently have a published product.
     */
    public function categories()
    {
        return Category::query()
            ->select(['id', 'name'])
            ->whereHas('products', function ($query) {
                $query->where('status', ProductStatus::PUBLISHED->value);
            })
            ->orderBy('name')
            ->get();
    }
}
