<?php

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    useVendor();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('guests can list published products and not drafts', function () {
    $publishedCategory = Category::factory()->create(['name' => 'Doors']);
    $draftCategory = Category::factory()->create(['name' => 'Hidden']);
    $owner = User::factory()->create();

    $published = Product::factory()->create([
        'user_id' => $owner->id,
        'category_id' => $publishedCategory->id,
        'status' => ProductStatus::PUBLISHED,
        'title' => 'Public Door',
        'base_sku' => 'DOOR-1',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $published->id,
        'price' => 1000,
        'sale_price' => 800,
        'attributes' => ['Color' => 'Red'],
    ]);

    Inventory::factory()->create([
        'variant_id' => $variant->id,
        'stock_quantity' => 10,
        'reserved_quantity' => 2,
    ]);

    $draft = Product::factory()->create([
        'user_id' => $owner->id,
        'category_id' => $draftCategory->id,
        'status' => ProductStatus::DRAFT,
        'title' => 'Secret Draft',
        'base_sku' => 'DOOR-2',
    ]);

    ProductVariant::factory()->create([
        'product_id' => $draft->id,
        'attributes' => ['Color' => 'Blue'],
    ]);

    $products = $this->getJson('/api/v1/stores/benta-door/catalog/products');

    $products->assertOk();
    $titles = collect($products->json('data'))->pluck('title');

    expect($titles)->toContain('Public Door')
        ->and($titles)->not->toContain('Secret Draft');

    expect($products->json('data.0.variants.0'))->not->toHaveKey('reserved_quantity')
        ->and($products->json('data.0.variants.0.stock'))->toEqual(8);

    $categories = $this->getJson('/api/v1/stores/benta-door/catalog/categories');

    $categories->assertOk();
    $names = collect($categories->json())->pluck('name');

    expect($names)->toContain('Doors')
        ->and($names)->not->toContain('Hidden');
});

test('a customer can check out without the staff create orders permission', function () {
    $customer = User::factory()->create();
    $owner = User::factory()->create();
    $category = Category::factory()->create();

    Permission::findOrCreate('create orders', 'api');

    $product = Product::factory()->create([
        'user_id' => $owner->id,
        'category_id' => $category->id,
        'status' => ProductStatus::PUBLISHED,
        'base_sku' => 'DOOR-3',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 1500,
        'sale_price' => 1200,
        'attributes' => ['Color' => 'Black'],
    ]);

    Inventory::factory()->create([
        'variant_id' => $variant->id,
        'stock_quantity' => 6,
        'reserved_quantity' => 0,
    ]);

    $payload = [
        'payment_method' => 'cash',
        'idempotency_key' => 'test-order',
        'discount' => 9999,
        'tax' => 50,
        'shipping_fee' => 80,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'price_type' => 'original',
            ],
        ],
    ];

    $this->actingAs($customer, 'api');

    $this->postJson('/api/v1/orders', $payload)->assertNotFound();

    $this->postJson('/api/v1/stores/benta-door/checkout', $payload)
        ->assertCreated()
        ->assertJsonPath('message', 'Order created successfully');

    useVendor();
    $order = Order::query()->first();

    expect($order)->not->toBeNull()
        ->and($order->user_id)->toBe($customer->id)
        ->and((float) $order->discount)->toBe(0.0)
        ->and((float) $order->tax)->toBe(0.0)
        ->and((float) $order->shipping_fee)->toBe(0.0)
        ->and((float) $order->total)->toBe(3000.0)
        ->and($customer->hasPermissionTo('create orders'))->toBeFalse();
});

test('a guest cannot check out', function () {
    $this->postJson('/api/v1/stores/benta-door/checkout', [
        'payment_method' => 'cash',
        'idempotency_key' => 'test-order',
        'items' => [
            ['variant_id' => 1, 'quantity' => 1, 'price_type' => 'original'],
        ],
    ])->assertUnauthorized();
});
