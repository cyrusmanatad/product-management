<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->boolean('is_primary')->default(false);
        });
        DB::statement('UPDATE product_images SET is_primary = TRUE WHERE id IN (SELECT id FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY vendor_id, product_id ORDER BY sort_order, id) AS position FROM product_images) ranked WHERE position = 1)');
        DB::statement('CREATE UNIQUE INDEX product_images_one_primary ON product_images (vendor_id, product_id) WHERE is_primary = TRUE');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_images_one_primary');
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn(['path', 'mime_type', 'is_primary']));
    }
};
