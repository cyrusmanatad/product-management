<?php

// app/Services/OrderService.php

namespace App\Services;

use App\DTOs\ProductData;
use App\Models\Inventory;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function getAll()
    {
        return Product::all();
    }

    /**
     * Create a new order with items
     */
    public function create(ProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Product
            $product = Product::create([
                'user_id' => $data->user_id,
                'category_id' => $data->category_id,
                'base_sku' => $data->base_sku,
                'title' => $data->title,
                'description' => $data->description ?? null,
                'slug' => $data->slug ?: Str::slug($data->title).'-'.strtolower((string) Str::ulid()),
                'status' => $data->status,
            ]);

            foreach ($data->variants as $item) {
                // 2. Create Variant
                $variant = $product->variants()->create([
                    'sku' => $item['sku'],
                    'uom' => $data->uom,
                    'price' => $item['price'],
                    'sale_price' => $item['sale_price'],
                    'currency' => app(TenantContext::class)->vendor->currency,
                    'attributes' => $item['attributes'],
                ]);

                // 3. Create Inventory for that Variant
                $variant->inventory()->create([
                    'stock_quantity' => $item['stock'] ?? 0,
                    'reserved_quantity' => 0,
                ]);
            }

            return $product->fresh(['variants.inventory']);
        });
    }

    public function update(Product $product, ProductData $data): bool
    {
        return DB::transaction(function () use ($product, $data) {
            $product->variants()->orderBy('id')->lockForUpdate()->get();
            Inventory::whereIn('variant_id', $product->variants()->pluck('id'))->orderBy('variant_id')->lockForUpdate()->get();
            // Update Product
            $product->update([
                'category_id' => $data->category_id,
                'base_sku' => $data->base_sku,
                'title' => $data->title,
                'description' => $data->description ?? null,
                'slug' => $data->slug ?: Str::slug($data->title).'-'.strtolower((string) Str::ulid()),
                'status' => $data->status,
            ]);

            // Get existing variant IDs to track deletions
            $existingVariantIds = $product->variants->pluck('id')->toArray();
            $updatedVariantIds = [];

            // Update or Create each variant
            foreach ($data->variants as $item) {
                $variant = $product->variants()->updateOrCreate(
                    ['sku' => $item['sku']],           // find by SKU
                    [
                        'sku' => $item['sku'],
                        'uom' => $data->uom,
                        'price' => $item['price'],
                        'sale_price' => $item['sale_price'],
                        'currency' => app(TenantContext::class)->vendor->currency,
                        'attributes' => $item['attributes'],
                        'is_active' => true,
                    ]
                );

                if (($variant->inventory?->reserved_quantity ?? 0) > ($item['stock'] ?? 0)) {
                    throw ValidationException::withMessages(['variants' => 'Stock cannot be lower than reserved quantity.']);
                }
                // Update or Create Inventory per variant
                $variant->inventory()->updateOrCreate(
                    ['variant_id' => $variant->id],
                    ['stock_quantity' => $item['stock'] ?? 0]
                );

                $updatedVariantIds[] = $variant->id;
            }

            // Soft delete variants that were removed from the payload
            $removedIds = array_diff($existingVariantIds, $updatedVariantIds);
            if (! empty($removedIds)) {
                $product->variants()
                    ->whereIn('id', $removedIds)
                    ->each(fn ($v) => $v->delete()); // respects observer & soft delete
            }

            return true;
        });
    }

    public function delete(Product $product): bool
    {
        return DB::transaction(function () use ($product) {
            // Cascade delete variants and inventory
            // (or rely on onDelete('cascade') in migrations)
            foreach ($product->variants as $variant) {
                $variant->delete();
            }

            return $product->delete();
        });
    }

    public function generateVariants(array $options, string $baseSku, $price = 0, $salePrice = null): array
    {
        $combinations = [[]];

        foreach ($options as $option) {
            $tmp = [];

            foreach ($combinations as $combination) {
                foreach ($option['values'] as $value) {
                    $tmp[] = array_merge($combination, [
                        $option['name'] => $value,
                    ]);
                }
            }

            $combinations = $tmp;
        }

        $variants = [];

        foreach ($combinations as $attributes) {
            $skuParts = [$baseSku];

            foreach ($attributes as $value) {
                $skuParts[] = strtoupper(str_replace([' ', '-'], '-', $value));
            }

            $variants[] = [
                'sku' => implode('-', $skuParts),
                'price' => $price,
                'sale_price' => $salePrice,
                'attributes' => $attributes,
            ];
        }

        return $variants;
    }

    public function baseQuery(): Builder
    {
        return Product::query()
            ->select(['id', 'user_id', 'category_id', 'title', 'slug', 'status', 'created_at'])
            ->with([
                'user:id,name,email',
                'category:id,name',
                'variants:id,product_id,sku,uom,price,sale_price,currency,is_active',
                'variants.inventory:id,variant_id,stock_quantity,reserved_quantity',
            ])
            ->withSum('inventories as total_stock', 'stock_quantity')
            ->withSum('inventories as total_reserved', 'reserved_quantity')
            ->orderByDesc('created_at');
    }

    public function applySearch(
        Builder $query,
        string $search
    ): Builder {
        return $query->where(function ($q) use ($search) {
            $q->where('slug', 'like', "%{$search}%")
                ->orWhereHas('variants', fn ($q2) => $q2->where('sku', 'like', "%{$search}%")
                    ->orWhere('desc', 'like', "%{$search}%")
                );
        });
    }
}
