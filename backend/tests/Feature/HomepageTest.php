<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorService;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->a = Vendor::firstOrFail();
    $this->b = Vendor::create(['name' => 'Other store', 'slug' => 'other']);
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_platform_admin' => true])->save();
    foreach ([$this->a, $this->b] as $vendor) {
        useVendor($vendor);
        app(VendorService::class)->seedRoles($vendor);
        joinVendor($this->owner, $vendor);
        $this->owner->unsetRelation('roles')->unsetRelation('permissions');
        $this->owner->assignRole(Role::where('vendor_id', $vendor->id)->where('name', 'Owner')->firstOrFail());
    }
    $this->pa = homepageProduct($this->a, $this->owner);
    $this->pb = homepageProduct($this->b, $this->owner);
    app(TenantContext::class)->clear();
});

function homepageProduct(Vendor $vendor, User $owner): Product
{
    useVendor($vendor);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['user_id' => $owner->id, 'category_id' => $category->id, 'title' => $vendor->name.' Door', 'slug' => 'shared-door', 'status' => 'published']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => '123.45', 'sale_price' => null, 'currency' => 'PHP', 'is_active' => true, 'attributes' => null]);
    Inventory::factory()->create(['variant_id' => $variant->id, 'stock_quantity' => 5, 'reserved_quantity' => 1]);

    return $product;
}

test('guests browse active stores with search and pagination without private vendor fields', function () {
    $this->getJson('/api/v1/homepage/stores')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.members')->assertJsonMissingPath('data.0.is_active');
    $this->getJson('/api/v1/homepage/stores?search=OTHER')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'other');
    $this->b->update(['is_active' => false]);
    $this->getJson('/api/v1/homepage/stores')->assertOk()->assertJsonCount(1, 'data');
    foreach (range(1, 13) as $index) {
        Vendor::create(['name' => 'Store '.$index, 'slug' => 'store-'.$index]);
    }
    $this->getJson('/api/v1/homepage/stores?page=2')->assertOk()->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.total', 14);
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(0, 'data');
});

test('only platform administrators can select or remove homepage products', function () {
    $path = '/api/v1/platform/vendors/benta-door/featured-products';
    $this->postJson($path, ['product_id' => $this->pa->id])->assertUnauthorized();
    $this->actingAs($this->owner, 'api')->getJson('/api/v1/platform/featured-products')->assertForbidden();
    $this->getJson('/api/v1/platform/vendors/benta-door/feature-candidates')->assertForbidden();
    $this->postJson($path, ['product_id' => $this->pa->id])->assertForbidden();
    $this->deleteJson($path.'/'.$this->pa->id)->assertForbidden();
    expect(DB::table('homepage_featured_products')->count())->toBe(0);
    $this->actingAs($this->admin, 'api')->postJson($path, ['product_id' => $this->pa->id])->assertOk();
    $this->postJson($path, ['product_id' => $this->pa->id])->assertOk();
    expect(DB::table('homepage_featured_products')->count())->toBe(1);
    expect(DB::table('vendor_memberships')->where('user_id', $this->admin->id)->count())->toBe(0);
    $this->getJson('/api/v1/vendors/benta-door/products')->assertForbidden();
    $this->admin->update(['is_active' => false]);
    $this->deleteJson($path.'/'.$this->pa->id)->assertForbidden();
    expect(DB::table('homepage_featured_products')->count())->toBe(1);
});

test('homepage curates public products across vendors and keeps selections vendor scoped and audited', function () {
    $this->actingAs($this->admin, 'api');
    $this->getJson('/api/v1/platform/vendors/benta-door/feature-candidates?search=DOOR')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->pa->id);
    $this->postJson('/api/v1/platform/vendors/benta-door/featured-products', ['product_id' => $this->pb->id])->assertUnprocessable();
    foreach ([[$this->a, $this->pa], [$this->b, $this->pb]] as [$vendor, $product]) {
        $this->postJson('/api/v1/platform/vendors/'.$vendor->slug.'/featured-products', ['product_id' => $product->id])->assertOk();
    }
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.store.slug', 'other')->assertJsonPath('data.1.store.slug', 'benta-door')
        ->assertJsonPath('data.0.price', '123.45')->assertJsonMissingPath('data.0.user_id')->assertJsonMissingPath('data.0.featured_by');
    $this->deleteJson('/api/v1/platform/vendors/benta-door/featured-products/'.$this->pb->id)->assertOk();
    expect(DB::table('homepage_featured_products')->count())->toBe(2);
    $this->deleteJson('/api/v1/platform/vendors/other/featured-products/'.$this->pb->id)->assertOk();
    $this->deleteJson('/api/v1/platform/vendors/other/featured-products/'.$this->pb->id)->assertOk();
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(1, 'data');
    expect(DB::table('audit_events')->where('action', 'homepage.product_featured')->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'homepage.product_unfeatured')->count())->toBe(1);
    expect(fn () => DB::transaction(fn () => DB::table('homepage_featured_products')->insert(['vendor_id' => $this->a->id, 'product_id' => $this->pb->id])))
        ->toThrow(QueryException::class);
});

test('unpublished deleted suspended and variant-ineligible products are hidden but removable', function () {
    $this->actingAs($this->admin, 'api');
    $path = '/api/v1/platform/vendors/benta-door/featured-products';
    $this->postJson($path, ['product_id' => $this->pa->id])->assertOk();
    DB::table('products')->where('vendor_id', $this->a->id)->where('id', $this->pa->id)->update(['status' => 'draft']);
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/platform/featured-products')->assertOk()->assertJsonPath('data.0.visible', false);
    $this->postJson($path, ['product_id' => $this->pa->id])->assertUnprocessable();
    DB::table('products')->where('vendor_id', $this->a->id)->where('id', $this->pa->id)->update(['status' => 'published']);
    foreach (['is_active' => false, 'currency' => 'USD', 'deleted_at' => now()] as $column => $value) {
        DB::table('product_variants')->where('vendor_id', $this->a->id)->where('product_id', $this->pa->id)->update([$column => $value]);
        $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($path, ['product_id' => $this->pa->id])->assertUnprocessable();
        DB::table('product_variants')->where('vendor_id', $this->a->id)->where('product_id', $this->pa->id)->update(['is_active' => true, 'currency' => 'PHP', 'deleted_at' => null]);
    }
    $this->a->update(['is_active' => false]);
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson($path, ['product_id' => $this->pa->id])->assertUnprocessable();
    $this->a->update(['is_active' => true]);
    DB::table('products')->where('vendor_id', $this->a->id)->where('id', $this->pa->id)->update(['deleted_at' => now()]);
    $this->getJson('/api/v1/homepage/featured-products')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/platform/featured-products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.visible', false);
    $this->deleteJson($path.'/'.$this->pa->id)->assertOk();
    expect(DB::table('homepage_featured_products')->count())->toBe(0);
});

test('featured product deep links resolve product slugs only inside their public store', function () {
    $this->getJson('/api/v1/stores/benta-door/catalog/products/shared-door')->assertOk()->assertJsonPath('data.id', $this->pa->id)->assertJsonPath('data.variants.0.attributes', [])->assertJsonPath('data.variants.0.stock', 4);
    $this->getJson('/api/v1/stores/other/catalog/products/shared-door')->assertOk()->assertJsonPath('data.id', $this->pb->id);
    $this->getJson('/api/v1/stores/other/catalog/products/missing')->assertNotFound();
    DB::table('products')->where('vendor_id', $this->a->id)->where('id', $this->pa->id)->update(['status' => 'draft']);
    $this->getJson('/api/v1/stores/benta-door/catalog/products/shared-door')->assertNotFound();
    expect(app(TenantContext::class)->id())->toBeNull();
});
