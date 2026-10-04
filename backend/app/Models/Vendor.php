<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'currency'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'vendor_memberships')->withPivot('is_active')->withTimestamps();
    }
}
