<?php

namespace App\Jobs\Middleware;

use App\Models\Vendor;
use App\Tenancy\TenantContext;

class WithinVendor
{
    public function __construct(public readonly int $vendorId) {}

    public function handle(object $job, callable $next): void
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            $context->vendor = Vendor::where('is_active', true)->findOrFail($this->vendorId);
            setPermissionsTeamId($this->vendorId);
            $next($job);
        } finally {
            $context->clear();
        }
    }
}
