<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('category seeds are owned by the selected vendor and can be repeated', function () {
    $initial = useVendor();
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);
    expect(Category::count())->toBe(6);
    expect(Category::where('level', '!=', 1)->count())->toBe(0);

    $other = Vendor::create(['name' => 'Other store', 'slug' => 'other-store', 'currency' => 'PHP', 'is_active' => true]);
    useVendor($other);
    $this->seed(CategorySeeder::class);
    expect(Category::count())->toBe(6);
    expect(DB::table('categories')->where('vendor_id', $initial->id)->count())->toBe(6);
    expect(DB::table('categories')->where('vendor_id', $other->id)->count())->toBe(6);
});

test('full demo seeds create vendor owned data and reset required staff accounts', function () {
    $this->seed(DatabaseSeeder::class);
    expect(app(TenantContext::class)->id())->toBeNull();
    $vendor = useVendor();
    $owner = User::where('email', 'cyrusmanatad@bentadoor.com')->firstOrFail();
    expect($owner->hasRole('Owner'))->toBeTrue();
    expect($owner->must_reset_password)->toBeTrue();
    expect($owner->is_platform_admin)->toBeFalse();
    expect(DB::table('vendor_memberships')->where('vendor_id', $vendor->id)->where('user_id', $owner->id)->exists())->toBeTrue();
    expect(Category::count())->toBe(6);
    expect(Product::count())->toBe(40);
    expect(Order::count())->toBe(10);
    expect(Inventory::whereColumn('reserved_quantity', '>', 'stock_quantity')->exists())->toBeFalse();
    expect(DB::table('vendor_customers')->where('vendor_id', $vendor->id)->count())->toBe(10);
    expect(DB::table('products')->whereNull('vendor_id')->count())->toBe(0);
});

test('failed demo seeding rolls back records and clears vendor context', function () {
    app()->bind(CategorySeeder::class, fn () => new class extends CategorySeeder
    {
        public function run(): void
        {
            throw new RuntimeException('Simulated category seed failure');
        }
    });

    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class, 'Simulated category seed failure');
    expect(User::count())->toBe(0);
    expect(DB::table('vendor_memberships')->count())->toBe(0);
    expect(app(TenantContext::class)->id())->toBeNull();
});
