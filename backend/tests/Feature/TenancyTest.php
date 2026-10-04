<?php

use App\Jobs\Middleware\WithinVendor;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\InvitationService;
use App\Services\VendorService;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->a = Vendor::firstOrFail();
    $this->b = Vendor::create(['name' => 'Other store', 'slug' => 'other']);
    $this->staff = User::factory()->create();
    $this->buyer = User::factory()->create();
    foreach ([$this->a, $this->b] as $vendor) {
        useVendor($vendor);
        app(VendorService::class)->seedRoles($vendor);
        joinVendor($this->staff, $vendor);
        $this->staff->unsetRelation('roles')->unsetRelation('permissions');
        $this->staff->assignRole(Role::where('vendor_id', $vendor->id)->where('name', 'Owner')->firstOrFail());
    }
    $this->variantA = tenantVariant($this->a, $this->staff, 'SHARED-SKU');
    $this->variantB = tenantVariant($this->b, $this->staff, 'SHARED-SKU');
});

function tenantVariant(Vendor $vendor, User $owner, string $sku): ProductVariant
{
    useVendor($vendor);
    $category = Category::factory()->create(['slug' => 'shared-category']);
    $product = Product::factory()->create(['user_id' => $owner->id, 'category_id' => $category->id, 'base_sku' => $sku, 'slug' => 'shared-product', 'status' => 'published', 'title' => $vendor->name.' item']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => $sku, 'price' => '10.25', 'sale_price' => '9.25', 'currency' => 'PHP']);
    Inventory::factory()->create(['variant_id' => $variant->id, 'stock_quantity' => 5, 'reserved_quantity' => 0]);

    return $variant;
}

function tenantCheckout(int $variant, string $key = 'checkout-one'): array
{
    return ['payment_method' => 'cash', 'idempotency_key' => $key, 'items' => [['variant_id' => $variant, 'quantity' => 2, 'price_type' => 'original']]];
}

test('public and staff catalogs isolate vendors including guessed product IDs', function () {
    $this->getJson('/api/v1/stores/benta-door/catalog/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Benta Door item');
    $this->actingAs($this->staff, 'api');
    $this->getJson('/api/v1/vendors/'.$this->a->slug.'/products')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/vendors/'.$this->a->slug.'/products/'.$this->variantB->product_id)->assertNotFound();
    $this->deleteJson('/api/v1/vendors/'.$this->a->slug.'/products/'.$this->variantB->product_id)->assertNotFound();
    $this->getJson('/api/v1/products')->assertNotFound();
    expect(app(TenantContext::class)->id())->toBeNull();
    expect(Product::count())->toBe(0);
});

test('checkout rejects foreign variants duplicates and wrong currency', function () {
    $this->actingAs($this->buyer, 'api');
    $this->postJson('/api/v1/stores/benta-door/checkout', tenantCheckout($this->variantB->id))->assertUnprocessable();
    $payload = tenantCheckout($this->variantA->id);
    $payload['items'][] = $payload['items'][0];
    $this->postJson('/api/v1/stores/benta-door/checkout', $payload)->assertUnprocessable();
    $this->postJson('/api/v1/stores/benta-door/checkout', [...tenantCheckout($this->variantA->id), 'currency' => 'USD'])->assertUnprocessable();
});

test('checkout is idempotent and uses exact server prices', function () {
    $this->actingAs($this->buyer, 'api');
    $payload = tenantCheckout($this->variantA->id);
    $first = $this->postJson('/api/v1/stores/benta-door/checkout', $payload)->assertCreated();
    $this->postJson('/api/v1/stores/benta-door/checkout', $payload)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
    $payload['items'][0]['quantity'] = 1;
    $this->postJson('/api/v1/stores/benta-door/checkout', $payload)->assertConflict();
    useVendor($this->a);
    expect(Order::count())->toBe(1)->and(Order::first()->total)->toBe('20.50')->and($this->variantA->inventory->stock_quantity)->toBe(3);
});

test('shared customers see only their own history in each store and staff can be customers', function () {
    $this->actingAs($this->staff, 'api');
    $this->postJson('/api/v1/stores/benta-door/checkout', tenantCheckout($this->variantA->id))->assertCreated();
    $this->postJson('/api/v1/stores/other/checkout', tenantCheckout($this->variantB->id))->assertCreated();
    $this->getJson('/api/v1/stores/benta-door/orders')->assertOk()->assertJsonCount(1, 'data.data');
    $this->getJson('/api/v1/vendors/'.$this->a->slug.'/customers/'.$this->staff->id)->assertOk()->assertJsonCount(1, 'data.order_history');
    $this->actingAs($this->buyer, 'api')->getJson('/api/v1/stores/benta-door/orders')->assertOk()->assertJsonCount(0, 'data.data');
});

test('staff URLs resolve store slugs and reject database IDs and unknown stores', function () {
    $this->actingAs($this->staff, 'api');
    $this->getJson('/api/v1/vendors/benta-door/me')->assertOk()->assertJsonPath('data.roles.0', 'Owner');
    $this->getJson('/api/v1/vendors/other/products')->assertOk()->assertJsonPath('data.0.id', $this->variantB->product_id);
    $this->getJson('/api/v1/vendors/'.$this->a->id.'/me')->assertNotFound();
    $this->getJson('/api/v1/vendors/missing-store/products')->assertNotFound();
    expect(app(TenantContext::class)->id())->toBeNull();
});

test('platform vendor status URLs use slugs including suspended stores', function () {
    $this->staff->forceFill(['is_platform_admin' => true])->save();
    $this->actingAs($this->staff, 'api');
    $this->patchJson('/api/v1/platform/vendors/other', ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson('/api/v1/vendors/other/me')->assertNotFound();
    $this->patchJson('/api/v1/platform/vendors/other', ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
    $this->getJson('/api/v1/vendors/other/me')->assertOk();
    $this->patchJson('/api/v1/platform/vendors/'.$this->b->id, ['is_active' => false])->assertNotFound();
    $this->patchJson('/api/v1/platform/vendors/missing-store', ['is_active' => false])->assertNotFound();
    expect($this->b->fresh()->is_active)->toBeTrue();
});

test('permissions and revocation are evaluated per membership', function () {
    useVendor($this->b);
    $this->staff->unsetRelation('roles')->unsetRelation('permissions')->syncRoles(['Support']);
    $this->actingAs($this->staff, 'api');
    $this->getJson('/api/v1/vendors/'.$this->a->slug.'/analytics/kpi')->assertOk();
    $this->getJson('/api/v1/vendors/'.$this->b->slug.'/analytics/kpi')->assertForbidden();
    DB::table('vendor_memberships')->where('vendor_id', $this->a->id)->where('user_id', $this->staff->id)->update(['is_active' => false]);
    $this->getJson('/api/v1/vendors/'.$this->a->slug.'/products')->assertForbidden();
    $this->getJson('/api/v1/vendors/'.$this->b->slug.'/products')->assertOk();
});

test('vendors cannot invite platform roles or edit protected owner roles', function () {
    $this->actingAs($this->staff, 'api');
    $this->postJson('/api/v1/vendors/'.$this->a->slug.'/users', ['email' => 'invite@example.com', 'role' => 'Owner'])->assertForbidden();
    $this->postJson('/api/v1/vendors/'.$this->a->slug.'/roles', ['name' => 'Super Admin', 'description' => 'bad', 'permissions' => ['view products']])->assertForbidden();
    $owner = Role::where('vendor_id', $this->a->id)->where('name', 'Owner')->firstOrFail();
    $this->deleteJson('/api/v1/vendors/'.$this->a->slug.'/roles/'.$owner->id)->assertForbidden();
    $this->postJson('/api/v1/platform/vendors', ['name' => 'bad'])->assertForbidden();
});

test('suspension and disabled accounts deny access immediately', function () {
    $this->a->update(['is_active' => false]);
    $this->getJson('/api/v1/stores/benta-door/catalog/products')->assertNotFound();
    $this->actingAs($this->staff, 'api')->getJson('/api/v1/vendors/'.$this->a->slug.'/products')->assertNotFound();
    $this->staff->update(['is_active' => false]);
    $this->getJson('/api/v1/users/me')->assertForbidden();
    $this->postJson('/api/v1/auth/login', ['email' => $this->staff->email, 'password' => 'password'])->assertUnauthorized();
});

test('cancellation restores inventory once and archives preserve orders', function () {
    $this->actingAs($this->buyer, 'api');
    $order = $this->postJson('/api/v1/stores/benta-door/checkout', tenantCheckout($this->variantA->id))->assertCreated()->json('data.id');
    $this->actingAs($this->staff, 'api');
    $path = '/api/v1/vendors/'.$this->a->slug.'/orders/'.$order;
    $this->putJson($path, ['status' => 'delivered'])->assertUnprocessable();
    $this->putJson($path, ['status' => 'cancelled'])->assertOk();
    $this->putJson($path, ['status' => 'cancelled'])->assertOk();
    $this->deleteJson($path)->assertOk();
    useVendor($this->a);
    expect($this->variantA->inventory->stock_quantity)->toBe(5)->and(Order::find($order)->archived_at)->not->toBeNull();
    $this->assertDatabaseHas('order_items', ['order_id' => $order]);
});

test('manual payments are audited scoped reversible and idempotent', function () {
    $this->actingAs($this->buyer, 'api');
    $id = $this->postJson('/api/v1/stores/benta-door/checkout', tenantCheckout($this->variantA->id))->assertCreated()->json('data.id');
    $this->actingAs($this->staff, 'api');
    $path = '/api/v1/vendors/'.$this->a->slug.'/orders/'.$id.'/payments';
    $this->postJson($path, ['reference' => 'BANK-1'])->assertCreated();
    $this->postJson($path, ['reference' => 'BANK-1'])->assertOk();
    $this->postJson($path, ['reference' => 'BANK-2'])->assertConflict();
    $payment = DB::table('manual_payments')->where('order_id', $id)->first();
    $this->postJson('/api/v1/vendors/'.$this->b->slug.'/orders/'.$id.'/payments/'.$payment->id.'/reverse', ['reason' => 'Wrong vendor'])->assertNotFound();
    $this->postJson($path.'/'.$payment->id.'/reverse', ['reason' => 'Recorded in error'])->assertOk();
    $this->postJson($path.'/'.$payment->id.'/reverse', ['reason' => 'Retry'])->assertOk();
    expect(DB::table('manual_payments')->count())->toBe(1)->and(DB::table('audit_events')->where('action', 'payment.reversed')->count())->toBe(1);
});

test('invitations are expiring single use and cannot reset an existing account', function () {
    $this->actingAs($this->staff, 'api');
    $response = $this->postJson('/api/v1/vendors/'.$this->a->slug.'/users', ['email' => $this->buyer->email, 'role' => 'Support'])->assertCreated();
    $token = basename($response->json('invitation_link'));
    $password = $this->buyer->password;
    $this->postJson('/api/v1/invitations/'.$token.'/accept', ['password' => 'changed-password', 'password_confirmation' => 'changed-password'])->assertForbidden();
    expect($this->buyer->fresh()->password)->toBe($password);
    $this->actingAs($this->buyer, 'api');
    $this->postJson('/api/v1/invitations/'.$token.'/accept')->assertOk();
    $this->postJson('/api/v1/invitations/'.$token.'/accept')->assertNotFound();
    $expired = app(InvitationService::class)->create($this->a, 'expired@example.com', 'Support');
    $token = basename($expired['invitation_link']);
    DB::table('vendor_invitations')->where('email', 'expired@example.com')->update(['expires_at' => now()->subMinute()]);
    $this->postJson('/api/v1/invitations/'.$token.'/accept')->assertNotFound();
});

test('platform administrators create vendors and owner invitations without implicit memberships', function () {
    $this->staff->forceFill(['is_platform_admin' => true])->save();
    $this->actingAs($this->staff, 'api');
    $response = $this->postJson('/api/v1/platform/vendors', ['name' => 'New vendor', 'slug' => 'new-vendor', 'owner_email' => 'new-owner@example.com'])->assertCreated();
    $vendor = $response->json('data.id');
    expect(DB::table('vendor_memberships')->where('vendor_id', $vendor)->count())->toBe(0);
    $token = basename($response->json('invitation_link'));
    $this->postJson('/api/v1/invitations/'.$token.'/accept', ['name' => 'New owner', 'password' => 'secure-owner-password', 'password_confirmation' => 'secure-owner-password'])->assertOk();
    $owner = User::where('email', 'new-owner@example.com')->firstOrFail();
    expect($owner->is_platform_admin)->toBeFalse();
    $this->actingAs($owner, 'api')->getJson('/api/v1/vendors/new-vendor/me')->assertOk()->assertJsonPath('data.roles.0', 'Owner');
});

test('database constraints reject cross-vendor categories and roles', function () {
    useVendor($this->b);
    $category = Category::firstOrFail();
    expect(fn () => DB::transaction(fn () => DB::table('products')->where('id', $this->variantA->product_id)->update(['category_id' => $category->id])))
        ->toThrow(QueryException::class);
    $role = Role::where('vendor_id', $this->b->id)->where('name', 'Support')->firstOrFail();
    expect(fn () => DB::transaction(fn () => DB::table('model_has_roles')->insert(['vendor_id' => $this->a->id, 'model_id' => $this->buyer->id, 'model_type' => User::class, 'role_id' => $role->id])))
        ->toThrow(QueryException::class);
});

test('order lists reports statistics and category writes remain vendor scoped', function () {
    $this->actingAs($this->buyer, 'api');
    $aOrder = $this->postJson('/api/v1/stores/benta-door/checkout', tenantCheckout($this->variantA->id))->assertCreated()->json('data.id');
    $bOrder = $this->postJson('/api/v1/stores/other/checkout', tenantCheckout($this->variantB->id))->assertCreated()->json('data.id');
    $this->actingAs($this->staff, 'api');
    $base = '/api/v1/vendors/'.$this->a->slug;
    $this->getJson($base.'/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aOrder);
    $this->getJson($base.'/orders/total')->assertOk()->assertJsonPath('data.total', 1);
    $this->putJson($base.'/orders/'.$bOrder, ['status' => 'confirmed'])->assertNotFound();
    $this->get($base.'/orders/export')->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->postJson($base.'/categories', ['name' => 'New category'])->assertCreated();
    useVendor($this->b);
    $foreign = Category::firstOrFail();
    $this->deleteJson($base.'/categories/'.$foreign->id)->assertNotFound();
});

test('default password accounts require an email reset and old tokens are revoked', function () {
    $user = User::factory()->create(['password' => 'Password@1234']);
    $user->forceFill(['must_reset_password' => true])->save();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password@1234'])->assertUnauthorized();
    $token = auth('api')->login($user);
    $reset = Password::createToken($user);
    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $reset, 'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'])->assertOk();
    expect($user->fresh()->must_reset_password)->toBeFalse();
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/users/me')->assertUnauthorized();
    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $reset, 'password' => 'other-secure-password', 'password_confirmation' => 'other-secure-password'])->assertUnprocessable();
});

test('background vendor context is cleared even if the job fails', function () {
    $middleware = new WithinVendor($this->a->id);
    expect(fn () => $middleware->handle(new stdClass, function () {
        expect(Product::count())->toBe(1);
        throw new RuntimeException('Failed job');
    }))->toThrow(RuntimeException::class);
    expect(app(TenantContext::class)->id())->toBeNull()->and(Product::count())->toBe(0);
});
