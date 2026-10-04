<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    public function upload(Product $product, array $files, ?int $primaryIndex): void
    {
        $paths = [];
        try {
            DB::transaction(function () use ($product, $files, $primaryIndex, &$paths) {
                $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                if ($product->images()->count() + count($files) > 10) {
                    throw ValidationException::withMessages(['images' => 'A product can have up to 10 images.']);
                }
                if (! $product->images()->exists()) {
                    $primaryIndex ??= 0;
                }
                if ($primaryIndex !== null) {
                    $product->images()->update(['is_primary' => false]);
                }
                $sortOrder = ($product->images()->max('sort_order') ?? -1) + 1;
                foreach ($files as $index => $file) {
                    $path = $file->store($product->vendor_id.'/'.$product->id, 'product_images');
                    $paths[] = $path;
                    $product->images()->create(['url' => '', 'path' => $path, 'mime_type' => $file->getMimeType(), 'sort_order' => $sortOrder + $index, 'is_primary' => $primaryIndex === $index]);
                }
                VendorService::audit('product.images_uploaded', ['product_id' => $product->id, 'count' => count($files)]);
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('product_images')->delete($path);
            }
            throw $exception;
        }
    }

    public function primary(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $image = $product->images()->whereKey($image->id)->firstOrFail();
            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
            VendorService::audit('product.image_primary_changed', ['product_id' => $product->id, 'image_id' => $image->id]);
        });
    }

    public function delete(Product $product, ProductImage $image): void
    {
        $path = DB::transaction(function () use ($product, $image) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $image = $product->images()->whereKey($image->id)->firstOrFail();
            $image->delete();
            if ($image->is_primary) {
                $product->images()->first()?->update(['is_primary' => true]);
            }
            VendorService::audit('product.image_deleted', ['product_id' => $product->id, 'image_id' => $image->id]);

            return $image->path;
        });
        if ($path) {
            Storage::disk('product_images')->delete($path);
        }
    }
}
