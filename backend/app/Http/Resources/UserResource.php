<?php

namespace App\Http\Resources;

use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = app(TenantContext::class)->id();
        $active = (bool) $this->is_active && (! $tenant || DB::table('vendor_memberships')->where('vendor_id', $tenant)->where('user_id', $this->id)->where('is_active', true)->exists());

        return [
            'id' => $this->id,
            'is_platform_admin' => $request->user()?->id === $this->id && (bool) $this->is_platform_admin,
            'must_reset_password' => (bool) $this->must_reset_password,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'is_active' => $active,
            'last_login_at' => $tenant ? null : $this->last_login_at?->toDateTimeString(),
            'last_login_ip' => $tenant ? null : $this->last_login_ip,
            'login' => $this->last_login_at?->diffForHumans() ?? 'Long time ago.',
            'status' => $active ? 'Active' : 'Inactive',
            'color' => $active ? 'green' : 'red',
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
