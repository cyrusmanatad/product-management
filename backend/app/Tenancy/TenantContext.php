<?php

namespace App\Tenancy;

use App\Models\Vendor;

class TenantContext
{
    public ?Vendor $vendor = null;

    public function id(): ?int
    {
        return $this->vendor?->id;
    }

    public function clear(): void
    {
        $this->vendor = null;
        setPermissionsTeamId(null);
    }
}
