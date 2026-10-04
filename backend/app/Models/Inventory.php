<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use BelongsToVendor;
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'stock_quantity',
        'reserved_quantity',
        'low_stock_threshold',
    ];

    protected $appends = ['humanize_datetime'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getHumanizeDatetimeAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }
}
