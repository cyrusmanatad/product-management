<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    /**
     * Seed 10 sales transactions with line items from existing variants.
     */
    public function run(): void
    {
        if (Order::query()->count() >= 10) {
            return;
        }

        $variants = ProductVariant::query()->with('product')->orderBy('id')->get();

        if ($variants->isEmpty()) {
            return;
        }

        $customers = User::query()
            ->whereNotIn('email', [
                'jules.conn@bentadoor.com',
                'bartell.toni@bentadoor.com',
                'bryce.douglas@bentadoor.org',
                'cyrusmanatad@bentadoor.com',
            ])
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($customers->isEmpty()) {
            return;
        }

        $blueprints = [
            ['status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cash', 'shipping_method' => 'pickup', 'quantities' => [1]],
            ['status' => 'confirmed', 'payment_status' => 'paid', 'payment_method' => 'gcash', 'shipping_method' => 'courier', 'quantities' => [2]],
            ['status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'credit_card', 'shipping_method' => 'courier', 'quantities' => [1, 1]],
            ['status' => 'shipped', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer', 'shipping_method' => 'courier', 'quantities' => [3]],
            ['status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'cash', 'shipping_method' => 'pickup', 'quantities' => [1]],
            ['status' => 'cancelled', 'payment_status' => 'unpaid', 'payment_method' => 'cash', 'shipping_method' => 'pickup', 'quantities' => [2]],
            ['status' => 'refunded', 'payment_status' => 'refunded', 'payment_method' => 'gcash', 'shipping_method' => 'courier', 'quantities' => [1]],
            ['status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'paymaya', 'shipping_method' => 'courier', 'quantities' => [1, 2]],
            ['status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'debit_card', 'shipping_method' => 'courier', 'quantities' => [1, 1, 1]],
            ['status' => 'processing', 'payment_status' => 'partial', 'payment_method' => 'bank_transfer', 'shipping_method' => 'courier', 'quantities' => [2, 1]],
        ];

        $variantIndex = 0;

        foreach ($blueprints as $index => $blueprint) {
            $customer = $customers[$index % $customers->count()];
            DB::table('vendor_customers')->insertOrIgnore([
                'vendor_id' => app(TenantContext::class)->id(),
                'user_id' => $customer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $items = $this->itemsFor($variants, $blueprint['quantities'], $variantIndex);
            $subtotal = round($items->sum(fn (array $item) => (float) $item['subtotal']), 2);
            $discount = $index % 3 === 0 ? 50 : 0;
            $tax = round($subtotal * 0.12, 2);
            $shippingFee = $blueprint['shipping_method'] === 'courier' ? 80 : 0;
            $paid = in_array($blueprint['payment_status'], ['paid', 'refunded'], true);

            $order = Order::query()->create([
                'order_number' => sprintf('ORD-%s-%05d', now()->year, $index + 1),
                'user_id' => $customer->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'shipping_fee' => $shippingFee,
                'total' => max(0, round($subtotal - $discount + $tax + $shippingFee, 2)),
                'currency' => 'PHP',
                'status' => $blueprint['status'],
                'payment_status' => $blueprint['payment_status'],
                'payment_method' => $blueprint['payment_method'],
                'paid_at' => $paid ? now()->subDays(9 - $index) : null,
                'shipping_method' => $blueprint['shipping_method'],
                'shipped_at' => in_array($blueprint['status'], ['shipped', 'delivered'], true) ? now()->subDays(8 - $index) : null,
                'delivered_at' => $blueprint['status'] === 'delivered' ? now()->subDays(7 - $index) : null,
                'notes' => 'Test transaction '.($index + 1),
            ]);

            $order->forceFill([
                'created_at' => now()->subDays(10 - $index)->setTime(9 + $index, 15),
                'updated_at' => now()->subDays(10 - $index)->setTime(9 + $index, 15),
            ])->save();

            foreach ($items as $item) {
                $order->items()->create($item);
            }
        }
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  array<int, int>  $quantities
     * @return Collection<int, array<string, mixed>>
     */
    private function itemsFor(Collection $variants, array $quantities, int &$variantIndex): Collection
    {
        return collect($quantities)->map(function (int $quantity) use ($variants, &$variantIndex) {
            $variant = $variants[$variantIndex % $variants->count()];
            $variantIndex++;

            $unitPrice = (float) $variant->price;
            $salePrice = (float) ($variant->sale_price ?? $variant->price);
            $priceType = $variant->sale_price ? 'sale' : 'original';
            $finalPrice = $priceType === 'sale' ? $salePrice : $unitPrice;
            $attributes = $variant->attributes;

            return [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $variant->product->title ?? $variant->sku,
                'variant_name' => $attributes ? collect($attributes)->values()->implode(' / ') : null,
                'uom' => $variant->uom,
                'unit_price' => $unitPrice,
                'sale_price' => $salePrice,
                'final_price' => $finalPrice,
                'price_type' => $priceType,
                'quantity' => $quantity,
                'subtotal' => round($finalPrice * $quantity, 2),
                'attributes' => $attributes,
            ];
        });
    }
}
