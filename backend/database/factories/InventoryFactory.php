<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stock = mt_rand(50, 99999);

        return [
            // 'variant_id' => ProductVariant::inRandomOrder()->first()->id ?? ProductVariant::factory(),
            'stock_quantity' => $stock,
            'reserved_quantity' => mt_rand(0, min(100, $stock)),
            'low_stock_threshold' => mt_rand(50, 100),
        ];
    }
}
