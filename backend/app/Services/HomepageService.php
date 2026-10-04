<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Vendor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class HomepageService
{
    /** Public catalog projection only; every product/variant/placement join preserves vendor ownership. */
    private function products(bool $visibleOnly = true): Builder
    {
        $variants = DB::table('product_variants as pv')
            ->whereColumn('pv.vendor_id', 'p.vendor_id')
            ->whereColumn('pv.product_id', 'p.id')
            ->whereColumn('pv.currency', 'v.currency')
            ->where('pv.is_active', true)->whereNull('pv.deleted_at');

        $query = DB::table('products as p')
            ->join('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->leftJoin('homepage_featured_products as f', fn ($join) => $join
                ->on('f.vendor_id', '=', 'p.vendor_id')->on('f.product_id', '=', 'p.id'))
            ->select(['p.id', 'p.title', 'p.slug', 'p.status', 'p.deleted_at', 'v.name as store_name', 'v.slug as store_slug', 'v.currency', 'v.is_active as store_active', 'f.id as feature_id'])
            ->selectSub((clone $variants)->selectRaw('MIN(pv.price)'), 'price')
            ->selectSub((clone $variants)->selectRaw('COUNT(*)'), 'variant_count')
            ->selectSub(DB::table('product_images as pi')->whereColumn('pi.vendor_id', 'p.vendor_id')->whereColumn('pi.product_id', 'p.id')->orderByDesc('pi.is_primary')->orderBy('pi.sort_order')->orderBy('pi.id')->select('pi.id')->limit(1), 'image_id')
            ->selectSub(DB::table('product_images as pi')->whereColumn('pi.vendor_id', 'p.vendor_id')->whereColumn('pi.product_id', 'p.id')->orderByDesc('pi.is_primary')->orderBy('pi.sort_order')->orderBy('pi.id')->selectRaw('CASE WHEN pi.path IS NULL THEN pi.url ELSE NULL END')->limit(1), 'legacy_image_url');

        if ($visibleOnly) {
            $query->where('v.is_active', true)->where('p.status', ProductStatus::PUBLISHED->value)
                ->whereNull('p.deleted_at')->whereExists((clone $variants)->selectRaw('1'));
        }

        return $query;
    }

    public function featured(bool $visibleOnly = true): Builder
    {
        return $this->products($visibleOnly)->whereNotNull('f.id')->orderByDesc('f.id');
    }

    public function candidates(Vendor $vendor, string $search = ''): Builder
    {
        return $this->products()->where('p.vendor_id', $vendor->id)
            ->when($search !== '', fn ($query) => $query->whereLike('p.title', '%'.$search.'%'))
            ->orderBy('p.title')->orderBy('p.id');
    }

    public function feature(Vendor $vendor, int $productId, int $actorId): void
    {
        DB::transaction(function () use ($vendor, $productId, $actorId) {
            abort_unless($this->candidates($vendor)->where('p.id', $productId)->exists(), 422, 'Choose a published product from an active store with an active variant.');
            $inserted = DB::table('homepage_featured_products')->insertOrIgnore([
                'vendor_id' => $vendor->id, 'product_id' => $productId, 'featured_by' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted) {
                VendorService::audit('homepage.product_featured', ['product_id' => $productId], vendorId: $vendor->id);
            }
        });
    }

    public function remove(Vendor $vendor, int $productId): void
    {
        DB::transaction(function () use ($vendor, $productId) {
            $removed = DB::table('homepage_featured_products')->where('vendor_id', $vendor->id)->where('product_id', $productId)->delete();
            if ($removed) {
                VendorService::audit('homepage.product_unfeatured', ['product_id' => $productId], vendorId: $vendor->id);
            }
        });
    }
}
