<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $color = $this->faker->randomElement(['Black', 'White']);
        $size = $this->faker->randomElement(['30-inch', '50-inch', '60-inch']);

        $useSize = $this->faker->boolean(); // true = Color+Size, false = Color only

        $attributes = [
            'Color' => $color,
        ];

        if ($useSize) {
            $attributes['Size'] = $size;
        }

        return [
            'sku' => $this->faker->unique()->bothify('####'),
            'uom' => $this->faker->randomElement(['pcs', 'box', 'kg', 'lt']),
            'price' => $this->faker->randomFloat(2, 500, 1000),
            'sale_price' => $this->faker->randomFloat(2, 400, 500),
            'currency' => 'PHP',
            'attributes' => json_encode($attributes),
        ];
    }
}
