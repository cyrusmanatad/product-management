<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use BelongsToVendor;

    protected $fillable = ['url', 'path', 'mime_type', 'sort_order', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function publicUrl(): string
    {
        return $this->path
            ? '/api/v1/stores/'.app(TenantContext::class)->vendor->slug.'/images/'.$this->id
            : $this->url;
    }
}
