<?php

namespace App\Http\Resources;

use App\Enums\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomepageProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'price' => $this->price,
            'primary_image_url' => $this->image_id ? ($this->legacy_image_url ?? '/api/v1/stores/'.$this->store_slug.'/images/'.$this->image_id) : null,
            'currency' => $this->currency,
            'store' => ['name' => $this->store_name, 'slug' => $this->store_slug],
            'feature_id' => $this->feature_id === null ? null : (int) $this->feature_id,
            'visible' => (bool) $this->store_active && $this->status === ProductStatus::PUBLISHED->value && $this->deleted_at === null && $this->variant_count > 0,
        ];
    }
}
