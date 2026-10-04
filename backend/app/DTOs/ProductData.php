<?php

namespace App\DTOs;

class ProductData
{
    public function __construct(
        public readonly ?int $category_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $uom,
        public readonly float $price,
        public readonly float $sale_price,
        public readonly string $status,
        public readonly int $stock,
        public readonly ?string $slug,
        public readonly ?string $currency,
        public readonly string $base_sku,
        public readonly array $options,
        public readonly array $variants,
        public readonly int $user_id,
    ) {}

    public static function fromRequest(array $data, int $userId): self
    {
        return new self(
            $data['category_id'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['uom'],
            $data['price'],
            $data['sale_price'],
            $data['status'],
            $data['stock'],
            $data['slug'] ?? null,
            $data['currency'] ?? null,
            $data['base_sku'],
            $data['options'] ?? [],
            $data['variants'],
            $userId
        );
    }
}
