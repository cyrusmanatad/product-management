<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

// A separate connection/schema rehearses the real legacy migration without touching test fixtures.
test('legacy migration preserves records totals attribution roles and default-password flags', function () {
    $default = DB::getDefaultConnection();
    $settings = config('database.connections.'.$default);
    $schema = 'migration_'.bin2hex(random_bytes(6));
    if ($settings['driver'] === 'pgsql') {
        DB::statement('CREATE SCHEMA '.$schema);
        $settings['search_path'] = $schema;
    } else {
        $settings = ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true];
    }
    config(['database.connections.rehearsal' => $settings, 'permission.teams' => false]);
    DB::setDefaultConnection('rehearsal');
    try {
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (str_contains($path, '2026_10_03')) {
                continue;
            }
            (require $path)->up();
        }
        $user = DB::table('users')->insertGetId(['name' => 'Legacy owner', 'email' => 'legacy@example.com', 'password' => Hash::make('Password@1234'), 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('categories')->insertGetId(['name' => 'Doors', 'slug' => 'doors']);
        $product = DB::table('products')->insertGetId(['user_id' => $user, 'category_id' => $category, 'base_sku' => 'LEGACY', 'title' => 'Door', 'description' => 'Preserve this', 'slug' => 'door', 'status' => 'published']);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'sku' => 'LEGACY-RED', 'uom' => 'pcs', 'price' => '123.45', 'currency' => 'PHP']);
        DB::table('inventories')->insert(['variant_id' => $variant, 'stock_quantity' => 17]);
        $order = DB::table('orders')->insertGetId(['user_id' => $user, 'order_number' => 'ORD-OLD-1', 'subtotal' => '123.45', 'total' => '123.45', 'status' => 'delivered', 'payment_status' => 'paid']);
        DB::table('order_items')->insert(['order_id' => $order, 'variant_id' => $variant, 'sku' => 'LEGACY-RED', 'product_name' => 'Door', 'uom' => 'pcs', 'unit_price' => '123.45', 'final_price' => '123.45', 'price_type' => 'original', 'quantity' => 1, 'subtotal' => '123.45']);
        $role = DB::table('roles')->insertGetId(['name' => 'Super Admin', 'guard_name' => 'api']);
        $permission = DB::table('permissions')->insertGetId(['name' => 'view products', 'guard_name' => 'api']);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => 'App\\Models\\User', 'model_id' => $user]);
        DB::table('model_has_permissions')->insert(['permission_id' => $permission, 'model_type' => 'App\\Models\\User', 'model_id' => $user]);
        DB::table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        DB::table('vendor_categories')->insert(['vendor_id' => $user, 'category_id' => $category]);
        $before = [];
        foreach (['products', 'product_variants', 'inventories', 'categories', 'orders', 'order_items'] as $table) {
            $before[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        config(['permission.teams' => true]);
        (require database_path('migrations/2026_10_03_000001_add_vendor_isolation.php'))->up();
        foreach ($before as $table => $records) {
            expect(DB::table($table)->count())->toBe(count($records));
            foreach ($records as $record) {
                $after = (array) DB::table($table)->where('id', $record['id'])->first();
                foreach ($record as $column => $value) {
                    expect($after[$column])->toBe($value);
                }
                expect((int) $after['vendor_id'])->toBe(1);
            }
        }
        expect((float) DB::table('orders')->sum('total'))->toBe(123.45);
        expect(DB::table('users')->where('id', $user)->value('must_reset_password'))->toBeTruthy();
        expect(DB::table('users')->where('id', $user)->value('is_platform_admin'))->toBeFalsy();
        expect(DB::table('roles')->where('id', $role)->value('name'))->toBe('Owner');
        expect(DB::table('model_has_roles')->where('model_id', $user)->value('vendor_id'))->toBe(1);
        expect(DB::table('model_has_permissions')->where('model_id', $user)->value('vendor_id'))->toBe(1);
        expect(DB::table('vendor_customers')->where('user_id', $user)->exists())->toBeTrue();
        expect(DB::table('vendor_memberships')->where('user_id', $user)->exists())->toBeTrue();
        expect(DB::table('vendor_categories')->value('legacy_user_id'))->toBe($user);
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('rehearsal');
        config(['permission.teams' => true]);
        if ($settings['driver'] === 'pgsql') {
            DB::statement('DROP SCHEMA '.$schema.' CASCADE');
        }
    }
});
