<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ['Electronics', 'Fashion', 'Home & Living', 'Beauty', 'Sports', 'Gadgets'];

        foreach ($categories as $index => $category) {
            Category::firstOrCreate(['slug' => Str::slug($category)], [
                'name' => $category,
                'path' => Str::slug($category),
                'level' => 1,
                'is_active' => 1,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
