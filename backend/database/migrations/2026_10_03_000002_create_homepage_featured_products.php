<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_featured_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->foreignId('featured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['vendor_id', 'product_id']);
            $table->foreign(['vendor_id', 'product_id'])->references(['vendor_id', 'id'])->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_featured_products');
    }
};
