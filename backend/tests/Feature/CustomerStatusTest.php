<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->vendor = useVendor();
    $this->other = Vendor::create(['name' => 'Other store', 'slug' => 'other-status']);
    $this->owner = User::factory()->create();
    $this->buyer = User::factory()->create();
    foreach ([$this->vendor, $this->other] as $vendor) {
        useVendor($vendor);
        app(VendorService::class)->seedRoles($vendor);
        joinVendor($this->owner, $vendor);
        joinVendor($this->buyer, $vendor);
        $this->owner->unsetRelation('roles')->assignRole(Role::where('vendor_id', $vendor->id)->where('name', 'Owner')->firstOrFail());
        DB::table('vendor_customers')->insert(['vendor_id' => $vendor->id, 'user_id' => $this->buyer->id, 'created_at' => now(), 'updated_at' => now()]);
        $product = Product::factory()->create(['user_id' => $this->owner->id, 'category_id' => Category::factory()->create()->id, 'status' => 'published']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => '10.00', 'currency' => 'PHP', 'is_active' => true]);
        Inventory::factory()->create(['variant_id' => $variant->id, 'stock_quantity' => 10, 'reserved_quantity' => 0]);
        $this->variants[$vendor->slug] = $variant->id;
    }
    $this->path = '/api/v1/vendors/benta-door/customers/'.$this->buyer->id.'/status';
});

function customerStatusCart(int $variant, string $key): array
{
    return ['payment_method' => 'cash', 'idempotency_key' => $key, 'items' => [['variant_id' => $variant, 'quantity' => 1, 'price_type' => 'original']]];
}

test('blocking is reversible vendor scoped and preserves identity membership and history', function () {
    $this->actingAs($this->buyer, 'api')->postJson('/api/v1/stores/benta-door/checkout', customerStatusCart($this->variants['benta-door'], 'before-block'))->assertCreated();
    $identity = $this->buyer->fresh()->getAttributes();
    $memberships = DB::table('vendor_memberships')->where('user_id', $this->buyer->id)->get()->toArray();
    $this->actingAs($this->owner, 'api')->patchJson($this->path, ['is_blocked' => true])->assertOk()->assertJsonPath('data.status', 'Blocked');
    $this->patchJson($this->path, ['is_blocked' => true])->assertOk();
    $this->getJson('/api/v1/vendors/benta-door/customers')->assertOk()->assertJsonPath('data.0.status', 'Blocked');
    $this->getJson('/api/v1/vendors/other-status/customers/'.$this->buyer->id)->assertOk()->assertJsonPath('data.status', 'Active');
    expect($this->buyer->fresh()->getAttributes())->toBe($identity);
    expect(DB::table('vendor_memberships')->where('user_id', $this->buyer->id)->get()->toArray())->toEqual($memberships);
    expect(DB::table('audit_events')->where('action', 'customer.status_changed')->count())->toBe(1);
    $this->actingAs($this->buyer, 'api')->getJson('/api/v1/users/me')->assertOk();
    $this->getJson('/api/v1/vendors/benta-door/me')->assertOk();
    $this->getJson('/api/v1/stores/benta-door/orders')->assertOk()->assertJsonPath('data.total', 1);
    $this->postJson('/api/v1/stores/benta-door/checkout', customerStatusCart($this->variants['benta-door'], 'before-block'))->assertCreated();
    $this->postJson('/api/v1/stores/benta-door/checkout', customerStatusCart($this->variants['benta-door'], 'blocked'))->assertForbidden();
    // Staff creation uses the same buyer identity and must enforce the same restriction.
    useVendor($this->vendor);
    Permission::findOrCreate('create orders', 'api');
    $this->buyer->givePermissionTo('create orders');
    $this->postJson('/api/v1/vendors/benta-door/orders', customerStatusCart($this->variants['benta-door'], 'staff-blocked'))->assertForbidden();
    $this->postJson('/api/v1/stores/other-status/checkout', customerStatusCart($this->variants['other-status'], 'other-store'))->assertCreated();
    expect(DB::table('orders')->count())->toBe(2);
    $this->actingAs($this->owner, 'api')->patchJson($this->path, ['is_blocked' => false])->assertOk()->assertJsonPath('data.status', 'Active');
    $this->actingAs($this->buyer, 'api')->postJson('/api/v1/stores/benta-door/checkout', customerStatusCart($this->variants['benta-door'], 'unblocked'))->assertCreated();
});

test('customer status requires both customer permissions and a current vendor relationship', function () {
    $this->patchJson($this->path, ['is_blocked' => true])->assertUnauthorized();
    foreach ([['view customers'], ['edit customers'], []] as $permissions) {
        $viewer = User::factory()->create();
        joinVendor($viewer, $this->vendor);
        $viewer->givePermissionTo($permissions);
        $this->actingAs($viewer, 'api')->patchJson($this->path, ['is_blocked' => true])->assertForbidden();
    }
    $this->actingAs($this->owner, 'api');
    $stranger = User::factory()->create();
    DB::table('vendor_customers')->insert(['vendor_id' => $this->other->id, 'user_id' => $stranger->id]);
    $this->patchJson('/api/v1/vendors/benta-door/customers/'.$stranger->id.'/status', ['is_blocked' => true])->assertNotFound();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin, 'api')->patchJson($this->path, ['is_blocked' => true])->assertForbidden();
    expect(DB::table('vendor_customers')->where('is_blocked', true)->count())->toBe(0);
});

test('customer status rejects invalid status and identity fields without changing the relationship', function () {
    $this->actingAs($this->owner, 'api');
    $this->patchJson($this->path, ['is_blocked' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('is_blocked');
    $this->patchJson($this->path, ['is_blocked' => true, 'email' => 'changed@example.com'])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(DB::table('vendor_customers')->where('is_blocked', true)->count())->toBe(0);
});
