<?php

namespace App\Http\Controllers;

use App\DTOs\OrderData;
use App\Enums\ProductStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /**
     * Place an order for the signed-in customer.
     * Discount, tax, and shipping are set here, not taken from the client.
     */
    public function index()
    {
        return response()->json(['data' => Order::where('user_id', Auth::id())->with('items')->orderByDesc('id')->paginate(20)]);
    }

    public function store(CheckoutRequest $request)
    {
        $payload = $request->validated();
        $variantIds = collect($payload['items'])->pluck('variant_id');

        $hasUnpublished = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->whereHas('product', function ($query) {
                $query->where('status', '!=', ProductStatus::PUBLISHED->value);
            })
            ->exists();

        if ($hasUnpublished) {
            throw ValidationException::withMessages([
                'items' => ['One or more items are not available.'],
            ]);
        }

        $payload['discount'] = 0;
        $payload['tax'] = 0;
        $payload['shipping_fee'] = 0;
        $payload['currency'] = $payload['currency'] ?? 'PHP';
        $payload['shipping_method'] = $payload['shipping_method'] ?? null;
        $payload['notes'] = $payload['notes'] ?? null;

        $dto = OrderData::fromRequest($payload, Auth::id());
        $order = $this->orderService->create($dto, $payload['idempotency_key'], hash('sha256', json_encode($payload)));

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }
}
