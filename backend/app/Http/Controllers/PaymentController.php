<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\VendorService;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $data = $request->validate(['reference' => 'required|string|max:150']);

        return DB::transaction(function () use ($request, $order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($order->status, ['cancelled', 'refunded']) || $order->archived_at, 422, 'Order cannot accept payment.');
            $existing = DB::table('manual_payments')->where('vendor_id', app(TenantContext::class)->id())->where('order_id', $order->id)->whereNull('reversed_at')->first();
            if ($existing) {
                abort_unless($existing->reference === $data['reference'], 409, 'Payment already recorded.');

                return response()->json(['data' => $order]);
            }
            abort_if($order->payment_status === 'paid', 422, 'Legacy paid order already has a recorded payment.');
            DB::table('manual_payments')->insert(['vendor_id' => app(TenantContext::class)->id(), 'order_id' => $order->id, 'amount_minor' => Money::minor($order->total), 'reference' => $data['reference'], 'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
            VendorService::audit('payment.recorded', ['reference' => $data['reference'], 'amount' => $order->total], $order->id);

            return response()->json(['data' => $order->fresh()], 201);
        });
    }

    public function reverse(Request $request, Order $order, string $payment)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        return DB::transaction(function () use ($order, $payment, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $record = DB::table('manual_payments')->where('id', $payment)->where('vendor_id', app(TenantContext::class)->id())->where('order_id', $order->id)->lockForUpdate()->first();
            abort_unless($record, 404);
            if (! $record->reversed_at) {
                DB::table('manual_payments')->where('id', $record->id)->update(['reversed_at' => now(), 'updated_at' => now()]);
                $order->update(['payment_status' => 'unpaid', 'paid_at' => null]);
                VendorService::audit('payment.reversed', ['payment_id' => $record->id, ...$data], $order->id);
            }

            return response()->json(['data' => $order->fresh()]);
        });
    }
}
