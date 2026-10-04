<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\VendorService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private function members()
    {
        return User::whereIn('id', DB::table('vendor_memberships')->where('vendor_id', app(TenantContext::class)->id())->select('user_id'));
    }

    private function target(User $user): void
    {
        abort_unless($this->members()->whereKey($user->id)->exists(), 404);
        abort_if($user->id === auth('api')->id() || $user->hasRole('Owner'), 403, 'Protected membership.');
    }

    public function index(Request $request)
    {
        $query = $this->members()->with('roles')->orderBy('name');
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%'));
        }

        return UserResource::collection($query->paginate(10));
    }

    public function store(Request $request, InvitationService $service)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'role' => 'required|string']);
        $role = Role::where('vendor_id', app(TenantContext::class)->id())->where('guard_name', 'api')->where('name', $data['role'])->firstOrFail();
        $this->assignable($request, $role);

        return response()->json($service->create(app(TenantContext::class)->vendor, $data['email'], $role->name), 201);
    }

    private function assignable(Request $request, Role $role): void
    {
        abort_if(in_array($role->name, VendorService::PROTECTED_ROLES), 403);
        abort_unless($role->permissions->pluck('name')->diff($request->user()->getAllPermissions()->pluck('name'))->isEmpty(), 403);
    }

    public function update_role(Request $request, User $user)
    {
        $this->target($user);
        $data = $request->validate(['role' => 'required|string']);
        $role = Role::where('vendor_id', app(TenantContext::class)->id())->where('guard_name', 'api')->where('name', $data['role'])->firstOrFail();
        $this->assignable($request, $role);
        $user->syncRoles([$role]);
        VendorService::audit('staff.role_changed', ['user_id' => $user->id, 'role' => $role->name]);

        return response()->json(['data' => new UserResource($user->fresh())]);
    }

    public function update_status(Request $request, User $user)
    {
        $this->target($user);
        $data = $request->validate(['is_active' => 'required|boolean']);
        DB::table('vendor_memberships')->where('vendor_id', app(TenantContext::class)->id())->where('user_id', $user->id)->update(['is_active' => $data['is_active'], 'updated_at' => now()]);
        VendorService::audit('staff.status_changed', ['user_id' => $user->id, ...$data]);

        return response()->json(['message' => 'Membership updated.']);
    }

    public function destroy(Request $request, User $user)
    {
        $this->target($user);
        $request->merge(['is_active' => false]);

        return $this->update_status($request, $user);
    }

    public function total()
    {
        return response()->json(['data' => ['total' => $this->members()->count(), 'admin' => $this->members()->role(['Owner', 'Admin'])->count(), 'non_admin' => $this->members()->role(['Support', 'Inventory Staff'])->count(), 'active' => DB::table('vendor_memberships')->where('vendor_id', app(TenantContext::class)->id())->where('is_active', true)->count()]]);
    }
}
