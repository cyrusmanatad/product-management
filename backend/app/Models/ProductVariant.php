<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use BelongsToVendor;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'uom',
        'price',
        'sale_price',
        'currency',
        'is_active',
        'attributes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    protected $appends = ['humanize_datetime'];

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $variant) {
            $attributes = $variant->attributes;
            $encoded = is_string($attributes) || $attributes === null
                ? $attributes
                : json_encode($attributes);

            $variant->attributes_hash = md5($encoded ?? '');
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class, 'variant_id');
    }

    public function getHumanizeDatetimeAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }
}
