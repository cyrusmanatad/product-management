<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToVendor;

    protected $fillable = [
        'order_id',
        'variant_id',
        'sku',
        'product_name',
        'variant_name',
        'uom',
        'unit_price',
        'sale_price',
        'final_price',
        'price_type',
        'quantity',
        'subtotal',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'unit_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
