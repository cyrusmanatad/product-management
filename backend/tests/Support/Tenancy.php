<?php

use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

function useVendor(?Vendor $vendor = null): Vendor
{
    $vendor ??= Vendor::firstOrFail();
    app(TenantContext::class)->vendor = $vendor;
    setPermissionsTeamId($vendor->id);

    return $vendor;
}

function joinVendor(User $user, ?Vendor $vendor = null): void
{
    $vendor = useVendor($vendor);
    DB::table('vendor_memberships')->insertOrIgnore(['vendor_id' => $vendor->id, 'user_id' => $user->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
}
