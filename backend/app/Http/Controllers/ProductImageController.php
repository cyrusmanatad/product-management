<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadProductImagesRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function __construct(private ProductImageService $service) {}

    public function index(Product $product)
    {
        return ProductImageResource::collection($product->images()->get());
    }

    public function store(UploadProductImagesRequest $request, Product $product)
    {
        $this->service->upload($product, array_values($request->file('images')), $request->filled('primary_index') ? $request->integer('primary_index') : null);

        return ProductImageResource::collection($product->images()->get());
    }

    public function primary(Product $product, ProductImage $image)
    {
        $this->service->primary($product, $image);

        return ProductImageResource::collection($product->images()->get());
    }

    public function destroy(Product $product, ProductImage $image)
    {
        $this->service->delete($product, $image);

        return ProductImageResource::collection($product->images()->get());
    }

    public function preview(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);

        return $this->file($image);
    }

    public function storefront(string $slug, ProductImage $image)
    {
        $product = $image->product;
        abort_unless($product && $product->status === 'published' && $product->variants()->where('is_active', true)->where('currency', app(TenantContext::class)->vendor->currency)->exists(), 404);

        return $this->file($image);
    }

    private function file(ProductImage $image)
    {
        abort_unless($image->path && Storage::disk('product_images')->exists($image->path), 404);

        return Storage::disk('product_images')->response($image->path, null, ['Content-Type' => $image->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], 'inline');
    }
}
