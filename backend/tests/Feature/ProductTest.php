<?php

namespace Tests\Feature;

use App\Models\Category;
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

    $this->category = Category::factory()->create();
    $this->user = User::factory()->create();

    foreach (['create products', 'edit products', 'delete products'] as $permission) {
        Permission::findOrCreate($permission, 'api');
    }

    $this->user->givePermissionTo([
        'create products',
        'edit products',
        'delete products',
    ]);

    joinVendor($this->user);
    $this->actingAs($this->user, 'api');
});

test('create product stores color and size variants with their prices', function () {
    $payload = shirtPayload($this->category->id, 'SHIRT');

    $response = $this->postJson('/api/v1/vendors/benta-door/products', $payload);

    $response->assertCreated()
        ->assertJsonPath('message', 'Product created successfully');

    useVendor();
    $product = Product::query()->where('base_sku', 'SHIRT')->first();

    expect($product)->not->toBeNull()
        ->and($product->title)->toBe('Cotton Shirt')
        ->and($product->user_id)->toBe($this->user->id)
        ->and($product->category_id)->toBe($this->category->id)
        ->and($product->variants)->toHaveCount(4);

    expect(variantByAttributes($product, 'Red', 'S'))
        ->price->toBe('1000.00')
        ->sale_price->toBe('900.00')
        ->sku->toBe('SHIRT-RED-S')
        ->and(variantByAttributes($product, 'Red', 'S')->inventory->stock_quantity)->toBe(5);

    expect(variantByAttributes($product, 'Red', 'M'))
        ->price->toBe('1100.00')
        ->sale_price->toBe('990.00')
        ->and(variantByAttributes($product, 'Red', 'M')->inventory->stock_quantity)->toBe(8);

    expect(variantByAttributes($product, 'Blue', 'S'))
        ->price->toBe('1200.00')
        ->sale_price->toBe('1050.00')
        ->and(variantByAttributes($product, 'Blue', 'S')->inventory->stock_quantity)->toBe(3);

    expect(variantByAttributes($product, 'Blue', 'M'))
        ->price->toBe('1300.00')
        ->sale_price->toBe('1150.00')
        ->and(variantByAttributes($product, 'Blue', 'M')->inventory->stock_quantity)->toBe(12);
});

test('editing a product updates that product and leaves the other product unchanged', function () {
    $this->postJson('/api/v1/vendors/benta-door/products', shirtPayload($this->category->id, 'SHIRT'))
        ->assertCreated();
    $this->postJson('/api/v1/vendors/benta-door/products', shirtPayload($this->category->id, 'PANTS', 'Work Pants'))
        ->assertCreated();

    useVendor();
    $shirt = Product::query()->where('base_sku', 'SHIRT')->firstOrFail();
    $pants = Product::query()->where('base_sku', 'PANTS')->firstOrFail();

    $payload = shirtPayload($this->category->id, 'SHIRT');
    $payload['title'] = 'Updated Cotton Shirt';
    $payload['description'] = 'Updated description';
    $payload['sale_price'] = 1400;
    $payload['variants'][0]['price'] = 1500;
    $payload['variants'][0]['sale_price'] = 1400;
    $payload['variants'][0]['stock'] = 20;

    $response = $this->putJson('/api/v1/vendors/benta-door/products/'.$shirt->id, $payload);

    $response->assertOk()
        ->assertJsonPath('message', 'Product updated successfully');

    useVendor();
    $shirt->refresh()->load('variants.inventory');
    $pants->refresh()->load('variants.inventory');

    expect($shirt->title)->toBe('Updated Cotton Shirt')
        ->and($shirt->description)->toBe('Updated description')
        ->and($shirt->base_sku)->toBe('SHIRT');

    expect(variantByAttributes($shirt, 'Red', 'S'))
        ->price->toBe('1500.00')
        ->sale_price->toBe('1400.00')
        ->and(variantByAttributes($shirt, 'Red', 'S')->inventory->stock_quantity)->toBe(20);

    expect(variantByAttributes($shirt, 'Blue', 'M'))
        ->price->toBe('1300.00')
        ->sale_price->toBe('1150.00');

    expect($pants->title)->toBe('Work Pants')
        ->and($pants->base_sku)->toBe('PANTS')
        ->and(variantByAttributes($pants, 'Red', 'S')->price)->toBe('1000.00')
        ->and(variantByAttributes($pants, 'Red', 'S')->sku)->toBe('PANTS-RED-S')
        ->and(variantByAttributes($pants, 'Blue', 'M')->price)->toBe('1300.00');
});

test('deleting a product removes that product and leaves the other product', function () {
    $this->postJson('/api/v1/vendors/benta-door/products', shirtPayload($this->category->id, 'SHIRT'))
        ->assertCreated();
    $this->postJson('/api/v1/vendors/benta-door/products', shirtPayload($this->category->id, 'PANTS', 'Work Pants'))
        ->assertCreated();

    useVendor();
    $shirt = Product::query()->where('base_sku', 'SHIRT')->firstOrFail();
    $pants = Product::query()->where('base_sku', 'PANTS')->firstOrFail();
    $shirtVariantIds = $shirt->variants()->pluck('id');
    $pantsVariantIds = $pants->variants()->pluck('id');

    $response = $this->deleteJson('/api/v1/vendors/benta-door/products/'.$shirt->id);

    $response->assertOk()
        ->assertJsonPath('message', 'Product deleted successfully');

    $this->assertSoftDeleted('products', ['id' => $shirt->id]);
    $this->assertDatabaseHas('products', [
        'id' => $pants->id,
        'base_sku' => 'PANTS',
        'title' => 'Work Pants',
        'deleted_at' => null,
    ]);

    foreach ($shirtVariantIds as $variantId) {
        $this->assertSoftDeleted('product_variants', ['id' => $variantId]);
        $this->assertDatabaseHas('inventories', ['variant_id' => $variantId]);
    }

    useVendor();
    expect(ProductVariant::query()->whereIn('id', $pantsVariantIds)->count())->toBe(4);

    foreach ($pantsVariantIds as $variantId) {
        $this->assertDatabaseHas('inventories', ['variant_id' => $variantId]);
    }
});

function shirtPayload(int $categoryId, string $baseSku, string $title = 'Cotton Shirt'): array
{
    $variants = [
        ['Color' => 'Red', 'Size' => 'S', 'price' => 1000, 'sale_price' => 900, 'stock' => 5],
        ['Color' => 'Red', 'Size' => 'M', 'price' => 1100, 'sale_price' => 990, 'stock' => 8],
        ['Color' => 'Blue', 'Size' => 'S', 'price' => 1200, 'sale_price' => 1050, 'stock' => 3],
        ['Color' => 'Blue', 'Size' => 'M', 'price' => 1300, 'sale_price' => 1150, 'stock' => 12],
    ];

    return [
        'category_id' => $categoryId,
        'base_sku' => $baseSku,
        'title' => $title,
        'description' => 'A shirt with color and size options',
        'uom' => 'pcs',
        'price' => 1000,
        'sale_price' => 900,
        'status' => 'published',
        'stock' => 28,
        'slug' => strtolower($baseSku).'-cotton',
        'currency' => 'PHP',
        'options' => [
            ['name' => 'Color', 'values' => ['Red', 'Blue']],
            ['name' => 'Size', 'values' => ['S', 'M']],
        ],
        'variants' => array_map(function (array $variant) use ($baseSku) {
            return [
                'sku' => $baseSku.'-'.strtoupper($variant['Color']).'-'.$variant['Size'],
                'price' => $variant['price'],
                'sale_price' => $variant['sale_price'],
                'stock' => $variant['stock'],
                'reserved_quantity' => 0,
                'attributes' => [
                    'Color' => $variant['Color'],
                    'Size' => $variant['Size'],
                ],
            ];
        }, $variants),
    ];
}

function variantByAttributes(Product $product, string $color, string $size): ProductVariant
{
    $product->loadMissing('variants.inventory');

    $variant = $product->variants->first(function (ProductVariant $variant) use ($color, $size) {
        return ($variant->attributes['Color'] ?? null) === $color
            && ($variant->attributes['Size'] ?? null) === $size;
    });

    expect($variant)->not->toBeNull();

    return $variant;
}
