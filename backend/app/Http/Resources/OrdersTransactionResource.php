<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdersTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => $this->user->name,
            'total' => $this->total,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'created_at' => $this->humanize_datetime,
            'items' => $this->items,
            'payments' => $this->whenLoaded('payments'),
            'color' => $this->color(),
        ];
    }

    private function color(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'confirmed' => 'orange',
            'processing' => 'teal',
            'shipped' => 'green',
            'delivered' => 'green',
            'refunded' => 'gray',
            default => 'red', // cancelled
        };
    }
}
