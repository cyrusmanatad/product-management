<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveVendor
{
    public function handle(Request $request, Closure $next, string $mode = 'staff')
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            $vendor = $mode === 'store'
                ? Vendor::where('slug', $request->route('slug'))->firstOrFail()
                : Vendor::where('slug', $request->route('vendor'))->firstOrFail();
            abort_unless($vendor->is_active, 404);
            if ($mode === 'staff') {
                $allowed = $request->user() && DB::table('vendor_memberships')
                    ->where('vendor_id', $vendor->id)->where('user_id', $request->user()->id)
                    ->where('is_active', true)->exists();
                if (! $allowed) {
                    Log::warning('Vendor access denied', ['vendor_id' => $vendor->id, 'user_id' => $request->user()?->id]);
                    abort(403);
                }
            }
            if ($mode === 'staff') {
                $request->route()->forgetParameter('vendor');
            }
            $context->vendor = $vendor;
            setPermissionsTeamId($vendor->id);
            $request->user()?->unsetRelation('roles')->unsetRelation('permissions');

            return $next($request);
        } finally {
            $request->user()?->unsetRelation('roles')->unsetRelation('permissions');
            $context->clear();
        }
    }
}
