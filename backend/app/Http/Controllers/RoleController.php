<?php

namespace App\Http\Controllers;

use App\Services\VendorService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private function roles()
    {
        return Role::where('vendor_id', app(TenantContext::class)->id())->where('guard_name', 'api');
    }

    public function index()
    {
        $roles = $this->roles()->with('permissions')->get()->map(fn ($role) => ['id' => $role->id, 'name' => $role->name, 'description' => $role->desc, 'color' => $role->color, 'users_count' => DB::table('model_has_roles')->where('vendor_id', app(TenantContext::class)->id())->where('role_id', $role->id)->count(), 'users' => [], 'permissions' => $role->permissions->pluck('name')]);

        return response()->json(['data' => $roles]);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('roles')->where('vendor_id', app(TenantContext::class)->id())->where('guard_name', 'api')->ignore($role?->id)], 'description' => 'required|string|max:500', 'permissions' => 'required|array', 'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'api')]]);
        abort_if(in_array($data['name'], VendorService::PROTECTED_ROLES), 403);
        abort_unless(collect($data['permissions'])->diff($request->user()->getAllPermissions()->pluck('name'))->isEmpty(), 403);

        return $data;
    }

    private function target(Role $role): void
    {
        abort_unless((int) $role->vendor_id === app(TenantContext::class)->id() && $role->guard_name === 'api', 404);
        abort_if(in_array($role->name, VendorService::PROTECTED_ROLES), 403);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return DB::transaction(function () use ($data) {
            $role = Role::create(['vendor_id' => app(TenantContext::class)->id(), 'name' => $data['name'], 'desc' => $data['description'], 'guard_name' => 'api']);
            $role->syncPermissions($data['permissions']);
            VendorService::audit('role.created', ['role_id' => $role->id]);

            return response()->json(['data' => $role->load('permissions')], 201);
        });
    }

    public function update(Request $request, Role $role)
    {
        $this->target($role);
        $data = $this->validated($request, $role);

        return DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name'], 'desc' => $data['description']]);
            $role->syncPermissions($data['permissions']);
            VendorService::audit('role.updated', ['role_id' => $role->id]);

            return response()->json(['data' => $role->load('permissions')]);
        });
    }

    public function destroy(Role $role)
    {
        $this->target($role);
        abort_if(DB::table('model_has_roles')->where('vendor_id', app(TenantContext::class)->id())->where('role_id', $role->id)->exists(), 422, 'Reassign staff before deleting their role.');
        $role->delete();
        VendorService::audit('role.deleted', ['role_id' => $role->id]);

        return response()->json(['message' => 'Role deleted.']);
    }

    public function permissions(Request $request)
    {
        return response()->json(['data' => $request->user()->getAllPermissions()->pluck('name')]);
    }
}
