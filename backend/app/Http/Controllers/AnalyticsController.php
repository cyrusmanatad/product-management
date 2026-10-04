<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Revenue Performance — current week vs previous week
     * GET /analytics/revenue
     */
    public function revenue(Request $request)
    {
        $currentStart = now()->startOfWeek();  // Monday
        $currentEnd = now()->endOfWeek();    // Sunday
        $previousStart = now()->subWeek()->startOfWeek();
        $previousEnd = now()->subWeek()->endOfWeek();

        $currentWeek = $this->revenueByDay($currentStart, $currentEnd);
        $previousWeek = $this->revenueByDay($previousStart, $previousEnd);

        // Total revenue for the week
        $currentTotal = array_sum($currentWeek);
        $previousTotal = array_sum($previousWeek);

        // Growth percentage
        $growth = $previousTotal > 0
            ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1)
            : 0;

        return response()->json([
            'data' => [
                'categories' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'current' => $currentWeek,
                'previous' => $previousWeek,
                'current_total' => number_format($currentTotal, 2),
                'prev_total' => number_format($previousTotal, 2),
                'growth' => $growth, // e.g. 12.5 means +12.5%
            ],
        ]);
    }

    /**
     * Helper — get revenue per day for a given date range
     * Returns array of 7 values [Mon, Tue, Wed, Thu, Fri, Sat, Sun]
     */
    private function revenueByDay(Carbon $start, Carbon $end): array
    {
        $totals = array_fill(0, 7, 0.0);

        Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')->where('currency', app(TenantContext::class)->vendor->currency)
            ->whereNotIn('status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->get(['created_at', 'total'])
            ->each(function (Order $order) use (&$totals) {
                $index = $order->created_at->dayOfWeekIso - 1;
                $totals[$index] += (float) $order->total;
            });

        return array_map(fn (float $total) => round($total, 2), $totals);
    }

    /**
     * Category Distribution
     * GET /analytics/categories
     */
    public function categories()
    {
        $categories = DB::table('orders')
            ->where('orders.vendor_id', app(TenantContext::class)->id())
            ->where('orders.payment_status', 'paid')->where('orders.currency', app(TenantContext::class)->vendor->currency)
            ->whereNull('orders.deleted_at')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'order_items.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereNotIn('orders.status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->select(
                'categories.name',
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('COUNT(order_items.id) as total_orders'),
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        $grandTotal = $categories->sum('total_revenue');

        $data = $categories->map(fn ($cat) => [
            'name' => $cat->name,
            'total_revenue' => round((float) $cat->total_revenue, 2),
            'total_orders' => (int) $cat->total_orders,
            'percentage' => $grandTotal > 0
                ? round(($cat->total_revenue / $grandTotal) * 100, 1)
                : 0,
        ]);

        return response()->json([
            'data' => [
                'labels' => $data->pluck('name')->values(),
                'series' => $data->pluck('total_revenue')->values(),
                'categories' => $data->values(),
            ],
        ]);
    }

    /**
     * KPI Summary
     * GET /analytics/kpi
     */
    public function kpi()
    {
        $currentStart = now()->startOfMonth();
        $previousStart = now()->subMonth()->startOfMonth();
        $previousEnd = now()->subMonth()->endOfMonth();

        // Net Revenue
        $currentRevenue = Order::whereBetween('created_at', [$currentStart, now()])
            ->where('payment_status', 'paid')->where('currency', app(TenantContext::class)->vendor->currency)
            ->whereNotIn('status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->sum('total');

        $previousRevenue = Order::whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('payment_status', 'paid')->where('currency', app(TenantContext::class)->vendor->currency)
            ->whereNotIn('status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->sum('total');

        // Average Order Value
        $currentOrderCount = Order::whereBetween('created_at', [$currentStart, now()])
            ->where('payment_status', 'paid')->where('currency', app(TenantContext::class)->vendor->currency)
            ->whereNotIn('status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->count();

        $previousOrderCount = Order::whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('payment_status', 'paid')->where('currency', app(TenantContext::class)->vendor->currency)
            ->whereNotIn('status', [
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->count();

        $currentAov = $currentOrderCount > 0 ? $currentRevenue / $currentOrderCount : 0;
        $previousAov = $previousOrderCount > 0 ? $previousRevenue / $previousOrderCount : 0;

        return response()->json([
            'data' => [
                'net_revenue' => [
                    'value' => number_format($currentRevenue, 2),
                    'raw' => $currentRevenue,
                    'trend' => $this->trend($currentRevenue, $previousRevenue),
                    'currency' => 'PHP',
                ],
                'avg_order_value' => [
                    'value' => number_format($currentAov, 2),
                    'raw' => round($currentAov, 2),
                    'trend' => $this->trend($currentAov, $previousAov),
                    'currency' => 'PHP',
                ],
            ],
        ]);
    }

    /**
     * Calculate trend percentage between current and previous value
     */
    private function trend(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'percentage' => 0,
                'direction' => 'up',
                'label' => '0%',
            ];
        }

        $percentage = round((($current - $previous) / $previous) * 100, 1);

        return [
            'percentage' => abs($percentage),
            'direction' => $percentage >= 0 ? 'up' : 'down',
            'label' => ($percentage >= 0 ? '+' : '-').abs($percentage).'%',
        ];
    }
}
