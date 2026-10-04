<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToVendor;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'base_sku',
        'title',
        'description',
        'slug',
        'status',
    ];

    protected $appends = ['humanize_datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventories(): HasManyThrough
    {
        return $this->hasManyThrough(
            Inventory::class,       // final model
            ProductVariant::class,  // intermediate model
            'product_id',           // FK on variants table
            'variant_id',           // FK on inventories table
            'id',                   // PK on products table
            'id'                    // PK on variants table
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getHumanizeDatetimeAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }
}
