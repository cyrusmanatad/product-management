<?php

use App\DTOs\OrderData;
use App\Models\Vendor;
use App\Services\OrderService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $schema, $user, $variant, $vendor, $key] = $argv;
if (! preg_match('/^concurrency_[a-f0-9]+$/', $schema)) {
    exit(2);
}
config(['database.connections.pgsql.search_path' => $schema, 'database.default' => 'pgsql']);
DB::purge('pgsql');
$app->make(TenantContext::class)->vendor = Vendor::findOrFail($vendor);
setPermissionsTeamId((int) $vendor);
file_put_contents(sys_get_temp_dir().'/'.$schema.'-'.$key.'-'.$user.'.ready', 'ready');
try {
    $data = OrderData::fromRequest(['currency' => 'PHP', 'payment_method' => 'cash', 'items' => [['variant_id' => (int) $variant, 'quantity' => 1, 'price_type' => 'original']]], (int) $user);
    $order = $app->make(OrderService::class)->create($data, $key, hash('sha256', 'same-cart'));
    echo json_encode(['id' => $order->id]);
} catch (ValidationException $e) {
    echo json_encode(['insufficient_stock' => true]);
} finally {
    $app->make(TenantContext::class)->clear();
}
