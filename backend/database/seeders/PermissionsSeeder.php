<?php

namespace Database\Seeders;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';

        // create permissions
        $permissions = [
            'create products',
            'view products',
            'edit products',
            'delete products',
            'create orders',
            'view orders',
            'edit orders',
            'delete orders',
            'create customers',
            'view customers',
            'edit customers',
            'delete customers',
            'create analytics',
            'view analytics',
            'edit analytics',
            'delete analytics',
            'create users',
            'view users',
            'edit users',
            'delete users',
            'create roles-permission',
            'view roles-permission',
            'edit roles-permission',
            'delete roles-permission',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guardName]);
        }

        // Access to own inbox, own customers, own products
        // $role1 = Role::create(['name' => 'vendor', 'guard_name' => $guardName, 'vendor_id' => app(\App\Tenancy\TenantContext::class)->id()]);
        // $role1->givePermissionTo(['create products', 'view products', 'edit own products', 'delete own products', 'publish own products', 'unpublish own products']);

        // Access to inbox, customers, products
        $role1 = Role::firstOrCreate(
            ['name' => 'Support', 'guard_name' => $guardName, 'vendor_id' => app(TenantContext::class)->id()],
            ['desc' => 'Support staff'],
        );
        $role1->syncPermissions(['view products', 'view customers', 'view orders']);

        // Manage products and stock
        $role2 = Role::firstOrCreate(
            ['name' => 'Inventory Staff', 'guard_name' => $guardName, 'vendor_id' => app(TenantContext::class)->id()],
            ['desc' => 'Inventory staff'],
        );
        $role2->syncPermissions(['create products', 'view products', 'edit products', 'delete products']);

        // Can manage shop items and orders
        $role3 = Role::firstOrCreate(
            ['name' => User::ROLE_ADMIN, 'guard_name' => $guardName, 'vendor_id' => app(TenantContext::class)->id()],
            ['desc' => 'System administrator'],
        );
        $role3->syncPermissions(['create products', 'view products', 'edit products', 'delete products', 'create orders', 'view orders', 'edit orders', 'delete orders']);

        // Full system access
        $role4 = Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => $guardName, 'vendor_id' => app(TenantContext::class)->id()],
            ['desc' => 'Super administrator'],
        );
        $role4->syncPermissions(Permission::all());
        // Gate::before in AppServiceProvider also grants this role every ability.

        $this->staffUser('Jules Conn', 'jules.conn@bentadoor.com', $role1);
        $this->staffUser('Bartell Toni', 'bartell.toni@bentadoor.com', $role2);
        $this->staffUser('Bryce Douglas', 'bryce.douglas@bentadoor.org', $role3);
        $this->staffUser('Cyrus Manatad', 'cyrusmanatad@bentadoor.com', $role4);
    }

    private function staffUser(string $name, string $email, Role $role): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => Hash::make('Password@1234'),
                'remember_token' => Str::random(10),
            ],
        );

        if (Hash::check('Password@1234', $user->password)) {
            $user->forceFill(['must_reset_password' => true])->save();
        }
        DB::table('vendor_memberships')->updateOrInsert(['vendor_id' => app(TenantContext::class)->id(), 'user_id' => $user->id], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
