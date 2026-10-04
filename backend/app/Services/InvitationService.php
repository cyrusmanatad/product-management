<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    public function create(Vendor $vendor, string $email, string $role): array
    {
        $token = Str::random(64);
        DB::table('vendor_invitations')->insert(['vendor_id' => $vendor->id, 'email' => strtolower($email), 'role' => $role, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
        VendorService::audit('staff.invited', ['email' => $email, 'role' => $role], vendorId: $vendor->id);

        return ['message' => 'Invitation created. Share this one-use link with the recipient.', 'invitation_link' => rtrim(config('app.url'), '/').'/invitations/'.$token];
    }
}
