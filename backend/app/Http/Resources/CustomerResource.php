<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalOrders = $this->orders->count();
        $lifetimeValue = $this->orders->sum('total');
        $completedOrders = $this->orders
            ->whereIn('status', ['delivered', 'completed'])
            ->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'joined' => $this->created_at?->diffForHumans(),

            // Order stats
            'orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'spent' => number_format($lifetimeValue, 2),
            'lv_raw' => $lifetimeValue, // raw for sorting/calculations

            // Activity
            'last_login_at' => null,
            'last_login_raw' => null,

            // Status
            'status' => $this->resolveStatus(),
            'color' => $this->resolveStatusColor(),
            'is_active' => $this->is_active,

            // Customer type
            'type' => $this->resolveCustomerType($totalOrders),
            'order_history' => $this->orders,
        ];
    }

    private function resolveStatus(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    private function resolveStatusColor(): string
    {
        return match ($this->resolveStatus()) {
            'Active' => 'green',
            'Idle' => 'yellow',
            'Inactive' => 'red',
            default => 'gray',
        };
    }

    private function resolveCustomerType(int $totalOrders): string
    {
        return match (true) {
            $totalOrders === 0 => 'New',
            $totalOrders <= 3 => 'Regular',
            $totalOrders <= 10 => 'Loyal',
            default => 'VIP',
        };
    }
}
