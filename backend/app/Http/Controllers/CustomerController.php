<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Models\User;
use App\Services\CustomerService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    private function customers()
    {
        return User::select('users.*')->selectSub(DB::table('vendor_customers')->where('vendor_id', app(TenantContext::class)->id())->whereColumn('user_id', 'users.id')->select('is_blocked'), 'customer_is_blocked')->whereIn('id', DB::table('vendor_customers')->where('vendor_id', app(TenantContext::class)->id())->select('user_id'));
    }

    public function index(Request $request)
    {
        $query = $this->customers()->with('orders')->orderBy('name');
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%'));
        }

        return CustomerResource::collection($query->paginate(min(100, max(1, (int) $request->input('per_page', 10)))));
    }

    public function show(User $user)
    {
        abort_unless($this->customers()->whereKey($user->id)->exists(), 404);
        $user = $this->customers()->whereKey($user->id)->with('orders.items')->firstOrFail();

        return new CustomerResource($user);
    }

    public function updateStatus(Request $request, User $user, CustomerService $service)
    {
        abort_unless($this->customers()->whereKey($user->id)->exists(), 404);
        $data = $request->validate(['is_blocked' => ['required', 'boolean'], 'email' => ['prohibited'], 'name' => ['prohibited'], 'is_active' => ['prohibited']]);
        $service->setBlocked($user->id, (bool) $data['is_blocked']);

        return new CustomerResource($this->customers()->whereKey($user->id)->with('orders')->firstOrFail());
    }

    public function total()
    {
        $total = $this->customers()->count();
        $retained = $this->customers()->has('orders', '>', 1)->count();

        return response()->json(['data' => ['total' => $total, 'new' => DB::table('vendor_customers')->where('vendor_id', app(TenantContext::class)->id())->where('created_at', '>=', now()->subDays(7))->count(), 'active' => $this->customers()->whereHas('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))->count(), 'retained' => $retained, 'retention' => $total ? round($retained / $total * 100, 2) : 0]]);
    }
}
