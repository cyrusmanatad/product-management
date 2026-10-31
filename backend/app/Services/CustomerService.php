<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function setBlocked(int $userId, bool $blocked): void
    {
        DB::transaction(function () use ($userId, $blocked) {
            // Use the same lock order as checkout so status changes serialize with orders.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $relationship = DB::table('vendor_customers')->where('vendor_id', app(TenantContext::class)->id())->where('user_id', $userId);
            $current = (clone $relationship)->lockForUpdate()->first();
            abort_unless($current, 404);
            if ((bool) $current->is_blocked === $blocked) {
                return;
            }
            $relationship->update(['is_blocked' => $blocked, 'updated_at' => now()]);
            VendorService::audit('customer.status_changed', ['user_id' => $userId, 'is_blocked' => $blocked]);
        });
    }
}
