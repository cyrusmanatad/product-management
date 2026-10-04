<?php

namespace App\Services;

use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VendorService
{
    public const PROTECTED_ROLES = ['Owner', 'Super Admin'];

    public function seedRoles(Vendor $vendor): void
    {
        $roles = [
            'Owner' => [],
            'Admin' => ['create products', 'view products', 'edit products', 'delete products', 'create orders', 'view orders', 'edit orders', 'delete orders', 'view customers', 'view analytics'],
            'Support' => ['view products', 'view orders', 'view customers'],
            'Inventory Staff' => ['create products', 'view products', 'edit products', 'delete products'],
        ];
        foreach (['products', 'orders', 'customers', 'analytics', 'users', 'roles-permission'] as $module) {
            foreach (['create', 'view', 'edit', 'delete'] as $action) {
                Permission::findOrCreate("{$action} {$module}", 'api');
            }
        }
        foreach ($roles as $name => $permissions) {
            $role = Role::firstOrCreate(['vendor_id' => $vendor->id, 'name' => $name, 'guard_name' => 'api'], ['desc' => $name]);
            $role->syncPermissions($name === 'Owner' ? Permission::where('guard_name', 'api')->get() : $permissions);
        }
    }

    public static function audit(string $action, array $details = [], ?int $orderId = null, ?int $vendorId = null): void
    {
        DB::table('audit_events')->insert(['vendor_id' => $vendorId ?? app(TenantContext::class)->id(), 'actor_id' => auth('api')->id(), 'action' => $action, 'order_id' => $orderId, 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }
}
