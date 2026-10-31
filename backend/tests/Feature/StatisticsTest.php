<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('inventory sales count paid non-cancelled units including archived history only within the vendor', function () {
    $vendor = useVendor();
    $staff = User::factory()->create();
    joinVendor($staff, $vendor);
    Permission::findOrCreate('view products', 'api');
    $staff->givePermissionTo('view products');
    $this->actingAs($staff, 'api');
    $this->getJson('/api/v1/vendors/benta-door/inventory/total')->assertOk()->assertJsonPath('data.total_units_sold', 0);
    foreach ([$vendor, Vendor::create(['name' => 'Other', 'slug' => 'other-stats'])] as $store) {
        useVendor($store);
        $product = Product::factory()->create(['user_id' => $staff->id, 'category_id' => Category::factory()->create()->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        foreach ([['paid', 'completed', 3], ['paid', 'cancelled', 8], ['unpaid', 'pending', 9], ['paid', 'completed', 2]] as $index => [$payment, $status, $quantity]) {
            $order = Order::create(['user_id' => $staff->id, 'order_number' => $store->id.'-STATS-'.$index, 'payment_status' => $payment, 'status' => $status]);
            $order->items()->create(['variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => 'Door', 'uom' => 'pcs', 'unit_price' => '10.00', 'final_price' => '10.00', 'price_type' => 'original', 'quantity' => $quantity, 'subtotal' => $quantity * 10]);
            if ($index === 3) {
                $order->delete();
            }
        }
    }
    $this->getJson('/api/v1/vendors/benta-door/inventory/total')->assertOk()->assertJsonPath('data.total_units_sold', 5);
});

test('customer statistics report store relationships recent orders and a repeat-customer percentage', function () {
    $this->travelTo(now()->startOfDay());
    $vendor = useVendor();
    $staff = User::factory()->create();
    joinVendor($staff, $vendor);
    Permission::findOrCreate('view customers', 'api');
    $staff->givePermissionTo('view customers');
    $this->actingAs($staff, 'api');
    $this->getJson('/api/v1/vendors/benta-door/customers/total')->assertOk()->assertJsonPath('data.retention', 0);
    useVendor($vendor);
    foreach (range(1, 4) as $index) {
        $buyer = User::factory()->create();
        DB::table('vendor_customers')->insert(['vendor_id' => $vendor->id, 'user_id' => $buyer->id, 'created_at' => $index === 1 ? now() : now()->subDays(20), 'updated_at' => now()]);
        if ($index === 1) {
            foreach (range(1, 2) as $number) {
                Order::create(['user_id' => $buyer->id, 'order_number' => 'CUSTOMER-STATS-'.$number]);
            }
        }
    }
    $this->getJson('/api/v1/vendors/benta-door/customers/total')->assertOk()->assertJsonPath('data.total', 4)->assertJsonPath('data.new', 1)->assertJsonPath('data.active', 1)->assertJsonPath('data.retained', 1)->assertJsonPath('data.retention', 25);
});
