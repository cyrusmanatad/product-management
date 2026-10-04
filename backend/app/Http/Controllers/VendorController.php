<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Vendor;
use App\Services\InvitationService;
use App\Services\VendorService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function memberships(Request $request)
    {
        $vendors = Vendor::query()->where('is_active', true)->whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id)->where('vendor_memberships.is_active', true))->get();

        return response()->json(['data' => $vendors]);
    }

    public function permissions(Request $request)
    {
        return new UserResource($request->user()->load('roles', 'permissions'));
    }

    public function storeInfo(string $slug)
    {
        return response()->json(['data' => app(TenantContext::class)->vendor]);
    }

    public function index()
    {
        return response()->json(['data' => Vendor::orderBy('id')->paginate(20)]);
    }

    public function store(Request $request, VendorService $service, InvitationService $invitations)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80|unique:vendors,slug', 'owner_email' => 'required|email|max:255']);

        return DB::transaction(function () use ($data, $service, $invitations) {
            $vendor = Vendor::create(['name' => $data['name'], 'slug' => $data['slug'], 'currency' => 'PHP']);
            $service->seedRoles($vendor);
            VendorService::audit('vendor.created', ['name' => $vendor->name], vendorId: $vendor->id);
            $invite = $invitations->create($vendor, $data['owner_email'], 'Owner');

            return response()->json(['data' => $vendor, ...$invite], 201);
        });
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $vendor->update($data);
        VendorService::audit('vendor.status_changed', $data, vendorId: $vendor->id);

        return response()->json(['data' => $vendor]);
    }
}
