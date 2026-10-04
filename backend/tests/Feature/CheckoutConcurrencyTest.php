<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('concurrent PostgreSQL checkouts cannot oversell and concurrent retries deduct only once', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL row locks.');
    }
    $default = DB::getDefaultConnection();
    $schema = 'concurrency_'.bin2hex(random_bytes(6));
    $settings = config('database.connections.'.$default);
    DB::statement('CREATE SCHEMA '.$schema);
    config(['database.connections.concurrent' => [...$settings, 'search_path' => $schema]]);
    DB::setDefaultConnection('concurrent');
    try {
        foreach (glob(database_path('migrations/*.php')) as $path) {
            (require $path)->up();
        }
        $vendor = useVendor(Vendor::firstOrFail());
        $buyers = [User::factory()->create(), User::factory()->create()];
        $category = Category::factory()->create();
        $product = Product::factory()->create(['user_id' => $buyers[0]->id, 'category_id' => $category->id, 'status' => 'published']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => '10.00', 'sale_price' => null, 'currency' => 'PHP']);
        $inventory = Inventory::factory()->create(['variant_id' => $variant->id, 'stock_quantity' => 1, 'reserved_quantity' => 0]);
        $run = function (array $users, array $keys) use ($schema, $variant, $vendor, $inventory) {
            DB::beginTransaction();
            DB::table('inventories')->where('id', $inventory->id)->lockForUpdate()->first();
            $workers = [];
            try {
                foreach ($users as $index => $user) {
                    $process = new Process([PHP_BINARY, base_path('tests/Support/checkout_worker.php'), $schema, (string) $user->id, (string) $variant->id, (string) $vendor->id, $keys[$index]], base_path(), timeout: 20);
                    $process->start();
                    $workers[] = $process;
                }
                $deadline = microtime(true) + 10;
                do {
                    $ready = true;
                    foreach ($users as $index => $user) {
                        $ready = $ready && file_exists(sys_get_temp_dir().'/'.$schema.'-'.$keys[$index].'-'.$user->id.'.ready');
                    }
                    if (! $ready) {
                        usleep(20000);
                    }
                } while (! $ready && microtime(true) < $deadline);
                expect($ready)->toBeTrue();
            } finally {
                DB::commit();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        };
        $results = $run($buyers, ['first-buyer', 'second-buyer']);
        expect(collect($results)->where('insufficient_stock', true)->count())->toBe(1);
        expect(DB::table('orders')->count())->toBe(1);
        expect(DB::table('inventories')->value('stock_quantity'))->toBe(0);
        DB::table('inventories')->where('id', $inventory->id)->update(['stock_quantity' => 1]);
        $results = $run([$buyers[0], $buyers[0]], ['same-retry', 'same-retry']);
        expect($results[0]['id'])->toBe($results[1]['id']);
        expect(DB::table('orders')->count())->toBe(2);
        expect(DB::table('inventories')->value('stock_quantity'))->toBe(0);
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        app(TenantContext::class)->clear();
        DB::setDefaultConnection($default);
        DB::purge('concurrent');
        DB::statement('DROP SCHEMA '.$schema.' CASCADE');
        foreach (glob(sys_get_temp_dir().'/'.$schema.'-*.ready') as $file) {
            unlink($file);
        }
    }
});
