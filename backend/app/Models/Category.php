<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToVendor;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'path',
        'level',
        'is_active',
        'sort_order',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
