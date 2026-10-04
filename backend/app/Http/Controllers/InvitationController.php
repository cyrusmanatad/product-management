<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invite = $this->query($token)->first();
        abort_unless($invite && $invite->accepted_at === null && now()->lt($invite->expires_at), 404);
        $vendor = Vendor::findOrFail($invite->vendor_id);
        abort_unless($vendor->is_active, 404);

        return response()->json(['data' => ['store' => $vendor->name, 'email' => $invite->email, 'existing_account' => User::where('email', $invite->email)->exists()]]);
    }

    public function accept(Request $request, string $token)
    {
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'password' => 'sometimes|required|string|min:12|not_in:Password@1234|confirmed']);

        return DB::transaction(function () use ($request, $token, $data) {
            $invite = $this->query($token)->lockForUpdate()->first();
            abort_unless($invite && $invite->accepted_at === null && now()->lt($invite->expires_at), 404);
            $vendor = Vendor::findOrFail($invite->vendor_id);
            abort_unless($vendor->is_active, 404);
            $user = User::where('email', $invite->email)->first();
            if ($user) {
                // Possession of an invitation must never reset an existing account password.
                abort_unless($request->user('api') && $request->user('api')->id === $user->id && $user->is_active && ! $user->must_reset_password, 403, 'Sign in as the invited account first.');
            } else {
                $request->validate(['name' => 'required|string|max:255', 'password' => 'required|string|min:12|not_in:Password@1234|confirmed']);
                $user = User::create(['name' => $data['name'], 'email' => $invite->email, 'password' => Hash::make($data['password'])]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            DB::table('vendor_memberships')->updateOrInsert(['vendor_id' => $vendor->id, 'user_id' => $user->id], ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            try {
                setPermissionsTeamId($vendor->id);
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $role = Role::where('vendor_id', $vendor->id)->where('name', $invite->role)->where('guard_name', 'api')->firstOrFail();
                $user->assignRole($role);
                DB::table('vendor_invitations')->where('id', $invite->id)->update(['accepted_at' => now()]);
                VendorService::audit('staff.invitation_accepted', ['user_id' => $user->id], vendorId: $vendor->id);

                return response()->json(['message' => 'Invitation accepted. Sign in to open your store.']);
            } finally {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                setPermissionsTeamId(null);
            }
        });
    }

    private function query(string $token)
    {
        return DB::table('vendor_invitations')->where('token_hash', hash('sha256', $token));
    }
}
