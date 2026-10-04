<?php

use App\Models\User;
use App\Services\VendorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform:grant {email}', function () {
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    $user->forceFill(['is_platform_admin' => true])->save();
    VendorService::audit('platform.admin_granted', ['user_id' => $user->id]);
    $this->info('Platform access granted to the reviewed account.');
});

Artisan::command('tenancy:verify', function () {
    $invalid = 0;
    foreach (['products', 'product_variants', 'inventories', 'categories', 'orders', 'order_items'] as $table) {
        $missing = DB::table($table)->whereNull('vendor_id')->count();
        $invalid += $missing;
        $this->info($table.': '.DB::table($table)->count().' rows; '.$missing.' missing vendor IDs');
    }
    foreach (['product_variants' => ['product_id', 'products'], 'inventories' => ['variant_id', 'product_variants'], 'order_items' => ['order_id', 'orders']] as $child => [$key, $parent]) {
        $invalid += DB::table($child.' as child')->join($parent.' as parent', 'parent.id', '=', 'child.'.$key)->whereColumn('child.vendor_id', '!=', 'parent.vendor_id')->count();
    }
    $invalid += DB::table('inventories')->where('stock_quantity', '<', 0)->orWhere('reserved_quantity', '<', 0)->orWhereColumn('stock_quantity', '<', 'reserved_quantity')->count();
    $this->info('Order total: '.DB::table('orders')->sum('total'));
    $this->info('Invalid ownership/inventory rows: '.$invalid);

    return $invalid ? 1 : 0;
});
