<?php

namespace App\Services;

use App\DTOs\OrderData;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(OrderData $data, ?string $key = null, ?string $requestHash = null): Order
    {
        return DB::transaction(function () use ($data, $key, $requestHash) {
            // Serializing checkout per buyer also makes the first idempotent request safe.
            DB::table('users')->where('id', $data->user_id)->lockForUpdate()->first();
            if ($key) {
                $existing = Order::where('user_id', $data->user_id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->request_hash, $requestHash), 409, 'Idempotency key was used for a different order.');

                    return $existing->load('items');
                }
            }
            $ids = $data->items->pluck('variant_id');
            if ($ids->unique()->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'Select each variant only once.']);
            }
            $variants = ProductVariant::with('product')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $inventories = Inventory::whereIn('variant_id', $ids)->orderBy('variant_id')->lockForUpdate()->get()->keyBy('variant_id');
            $subtotal = 0;
            $lines = [];
            foreach ($data->items as $item) {
                $variant = $variants->get($item->variant_id);
                $inventory = $inventories->get($item->variant_id);
                if (! $variant || ! $variant->is_active || ! $variant->product || $variant->product->status !== 'published' || ! $inventory || $item->quantity > $inventory->stock_quantity - $inventory->reserved_quantity || $item->quantity < 1) {
                    throw ValidationException::withMessages(['items' => 'One or more variants are unavailable or have insufficient stock.']);
                }
                abort_unless($variant->currency === app(TenantContext::class)->vendor->currency, 422, 'Product currency does not match the store.');
                $price = Money::minor($item->price_type === 'sale' && $variant->sale_price !== null ? $variant->sale_price : $variant->price);
                $lineTotal = $price * $item->quantity;
                $subtotal += $lineTotal;
                $lines[] = ['variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => $variant->product->title, 'variant_name' => collect($variant->attributes)->values()->implode(' / '), 'uom' => $variant->uom, 'unit_price' => $variant->price, 'sale_price' => $variant->sale_price, 'final_price' => Money::decimal($price), 'price_type' => $item->price_type, 'attributes' => $variant->attributes, 'quantity' => $item->quantity, 'subtotal' => Money::decimal($lineTotal)];
            }
            $discount = Money::minor($data->discount);
            $tax = Money::minor($data->tax);
            $shipping = Money::minor($data->shipping_fee);
            abort_if($discount > $subtotal, 422, 'Discount exceeds subtotal.');
            $order = Order::create(['order_number' => 'ORD-'.Str::ulid(), 'user_id' => $data->user_id, 'subtotal' => Money::decimal($subtotal), 'tax' => Money::decimal($tax), 'discount' => Money::decimal($discount), 'shipping_fee' => Money::decimal($shipping), 'total' => Money::decimal($subtotal - $discount + $tax + $shipping), 'currency' => app(TenantContext::class)->vendor->currency, 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => $data->payment_method, 'shipping_method' => $data->shipping_method, 'notes' => $data->notes, 'idempotency_key' => $key, 'request_hash' => $requestHash]);
            foreach ($lines as $line) {
                $order->items()->create($line);
                $inventories[$line['variant_id']]->decrement('stock_quantity', $line['quantity']);
            }
            DB::table('vendor_customers')->insertOrIgnore(['vendor_id' => app(TenantContext::class)->id(), 'user_id' => $data->user_id, 'created_at' => now(), 'updated_at' => now()]);
            VendorService::audit('order.created', [], $order->id);

            return $order->load('items');
        }, 3);
    }

    public function transition(Order $order, string $next): Order
    {
        return DB::transaction(function () use ($order, $next) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === $next) {
                return $order;
            }
            $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => [], 'refunded' => []];
            abort_unless(in_array($next, $allowed[$order->status] ?? []), 422, 'Invalid order transition.');
            if ($next === 'cancelled') {
                foreach ($order->items()->orderBy('variant_id')->get() as $item) {
                    $inventory = Inventory::where('variant_id', $item->variant_id)->lockForUpdate()->firstOrFail();
                    $inventory->increment('stock_quantity', $item->quantity);
                }
            }
            $before = $order->status;
            $changes = ['status' => $next];
            if ($next === 'shipped') {
                $changes['shipped_at'] = now();
            }
            if ($next === 'delivered') {
                $changes['delivered_at'] = now();
            }
            $order->update($changes);
            VendorService::audit('order.status_changed', ['from' => $before, 'to' => $next], $order->id);

            return $order->load('items');
        }, 3);
    }
}
