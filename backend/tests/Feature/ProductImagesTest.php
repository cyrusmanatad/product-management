<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProductImageService;
use App\Services\VendorService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function imageUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('door.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
}

beforeEach(function () {
    Storage::fake('product_images');
    $this->vendor = Vendor::firstOrFail();
    useVendor($this->vendor);
    $this->owner = User::factory()->create();
    app(VendorService::class)->seedRoles($this->vendor);
    joinVendor($this->owner, $this->vendor);
    $this->owner->assignRole(Role::where('vendor_id', $this->vendor->id)->where('name', 'Owner')->firstOrFail());
    $this->product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'user_id' => $this->owner->id, 'status' => 'published']);
    ProductVariant::factory()->create(['product_id' => $this->product->id, 'currency' => $this->vendor->currency, 'is_active' => true]);
    $this->path = '/api/v1/vendors/'.$this->vendor->slug.'/products/'.$this->product->id.'/images';
    $this->actingAs($this->owner, 'api');
});

test('owner uploads multiple images chooses cover and catalog keeps internal paths private', function () {
    $response = $this->post($this->path, ['images' => [imageUpload(), imageUpload()], 'primary_index' => 1], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.is_primary', true)->assertJsonPath('data.1.is_primary', false)->assertJsonMissingPath('data.0.path');
    $imageId = $response->json('data.0.id');
    useVendor($this->vendor);
    $images = ProductImage::all();
    expect($images->where('is_primary', true)->count())->toBe(1);
    foreach ($images as $image) {
        expect($image->path)->toStartWith($this->vendor->id.'/'.$this->product->id.'/');
        Storage::disk('product_images')->assertExists($image->path);
    }
    $this->getJson('/api/v1/stores/'.$this->vendor->slug.'/catalog/products/'.$this->product->slug)->assertOk()->assertJsonPath('data.images.0.id', $imageId)->assertJsonPath('data.primary_image_url', '/api/v1/stores/'.$this->vendor->slug.'/images/'.$imageId);
    $this->get('/api/v1/stores/'.$this->vendor->slug.'/images/'.$imageId)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->getJson('/api/v1/vendors/'.$this->vendor->slug.'/products')->assertOk()->assertJsonPath('data.0.images.0.id', $imageId);
});

test('default cover persists across uploads and deleting cover promotes another image', function () {
    $first = $this->post($this->path, ['images' => [imageUpload(), imageUpload()]], ['Accept' => 'application/json'])->assertOk()->json('data.0');
    $this->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.0.id', $first['id']);
    $all = $this->getJson($this->path)->assertOk()->json('data');
    $this->patchJson($this->path.'/'.$all[2]['id'].'/primary')->assertOk()->assertJsonPath('data.0.id', $all[2]['id']);
    useVendor($this->vendor);
    $removedPath = ProductImage::findOrFail($all[2]['id'])->path;
    $this->deleteJson($this->path.'/'.$all[2]['id'])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.is_primary', true);
    Storage::disk('product_images')->assertMissing($removedPath);
    $this->deleteJson($this->path.'/'.$all[0]['id'])->assertOk();
    $this->deleteJson($this->path.'/'.$all[1]['id'])->assertOk()->assertJsonCount(0, 'data');
});

test('uploads validate files indexes and maximum product image count without side effects', function () {
    $this->post($this->path, ['images' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')]], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post($this->path, ['images' => [UploadedFile::fake()->create('huge.png', 5121, 'image/png')]], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post($this->path, ['images' => [imageUpload()], 'primary_index' => 2], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post($this->path, ['images' => []], ['Accept' => 'application/json'])->assertUnprocessable();
    expect(Storage::disk('product_images')->allFiles())->toBeEmpty();
    $this->post($this->path, ['images' => array_map(fn () => imageUpload(), range(1, 10))], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(10, 'data');
    $this->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertUnprocessable();
    expect(Storage::disk('product_images')->allFiles())->toHaveCount(10);
});

test('images cannot be edited previewed or read through another product or store', function () {
    $id = $this->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertOk()->json('data.0.id');
    useVendor($this->vendor);
    $other = Product::factory()->create(['user_id' => $this->owner->id, 'category_id' => $this->product->category_id]);
    $otherPath = '/api/v1/vendors/'.$this->vendor->slug.'/products/'.$other->id.'/images/'.$id;
    $this->patchJson($otherPath.'/primary')->assertNotFound();
    $this->deleteJson($otherPath)->assertNotFound();
    $this->getJson($otherPath.'/file')->assertNotFound();
    $vendor = Vendor::create(['name' => 'Other', 'slug' => 'other']);
    $this->get('/api/v1/stores/other/images/'.$id)->assertNotFound();
    useVendor($vendor);
    app(VendorService::class)->seedRoles($vendor);
    joinVendor($this->owner, $vendor);
    $this->owner->unsetRelation('roles')->unsetRelation('permissions')->assignRole(Role::where('vendor_id', $vendor->id)->where('name', 'Owner')->firstOrFail());
    $this->getJson('/api/v1/vendors/other/products/'.$this->product->id.'/images')->assertNotFound();
    $this->post('/api/v1/vendors/other/products/'.$this->product->id.'/images', ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertNotFound();
});

test('guests and platform administrators cannot manage store product images', function () {
    auth('api')->forgetUser();
    $this->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertUnauthorized();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin, 'api')->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertForbidden();
    expect(Storage::disk('product_images')->allFiles())->toBeEmpty();
});

test('private draft and unavailable store files stay private while owner can preview drafts', function () {
    $id = $this->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertOk()->json('data.0.id');
    $url = '/api/v1/stores/'.$this->vendor->slug.'/images/'.$id;
    useVendor($this->vendor);
    $this->product->update(['status' => 'draft']);
    $this->get($url)->assertNotFound();
    $this->get($this->path.'/'.$id.'/file')->assertOk();
    useVendor($this->vendor);
    $this->product->update(['status' => 'published']);
    $this->vendor->update(['is_active' => false]);
    $this->get($url)->assertNotFound();
    $this->vendor->update(['is_active' => true]);
    useVendor($this->vendor);
    $this->product->delete();
    $this->get($url)->assertNotFound();
});

test('failed database save cleans up files and restores previous primary image', function () {
    $id = $this->post($this->path, ['images' => [imageUpload()]], ['Accept' => 'application/json'])->assertOk()->json('data.0.id');
    useVendor($this->vendor);
    ProductImage::creating(function () {
        throw new RuntimeException('Simulated persistence failure');
    });
    try {
        expect(fn () => app(ProductImageService::class)->upload($this->product, [imageUpload()], 0))->toThrow(RuntimeException::class);
        expect(Storage::disk('product_images')->allFiles())->toHaveCount(1);
        expect(ProductImage::findOrFail($id)->is_primary)->toBeTrue();
    } finally {
        ProductImage::flushEventListeners();
    }
});

test('homepage shows the selected cover and the database prevents duplicate primary images', function () {
    $images = $this->post($this->path, ['images' => [imageUpload(), imageUpload()], 'primary_index' => 1], ['Accept' => 'application/json'])->assertOk()->json('data');
    DB::table('homepage_featured_products')->insert(['vendor_id' => $this->vendor->id, 'product_id' => $this->product->id]);
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonPath('data.0.primary_image_url', '/api/v1/stores/'.$this->vendor->slug.'/images/'.$images[0]['id']);
    expect(fn () => DB::transaction(fn () => DB::table('product_images')->where('vendor_id', $this->vendor->id)->where('id', $images[1]['id'])->update(['is_primary' => true])))
        ->toThrow(QueryException::class);
});
