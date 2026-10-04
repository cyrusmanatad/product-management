<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToVendor, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'order_number',
        'archived_at',
        'idempotency_key',
        'request_hash',
        'user_id',
        'subtotal',
        'tax',
        'discount',
        'shipping_fee',
        'total',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'paid_at',
        'shipping_method',
        'shipped_at',
        'delivered_at',
        'notes',
    ];

    protected $hidden = ['idempotency_key', 'request_hash'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'discount' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'archived_at' => 'datetime'];
    }

    protected $appends = ['humanize_datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(ManualPayment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getHumanizeDatetimeAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }
}
