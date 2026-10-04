<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('currency', 3)->default('PHP');
            $table->timestamps();
        });
        $vendor = DB::table('vendors')->insertGetId(['name' => 'Benta Door', 'slug' => 'benta-door', 'currency' => 'PHP', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
            $table->boolean('must_reset_password')->default(false);
            $table->unsignedInteger('token_version')->default(0);
        });
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                if (Hash::check('Password@1234', $user->password)) {
                    DB::table('users')->where('id', $user->id)->update(['must_reset_password' => true]);
                }
            }
        });
        Schema::create('vendor_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['vendor_id', 'user_id']);
        });
        $staff = DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->pluck('model_id')->merge(DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->pluck('model_id'))->unique();
        foreach ($staff as $userId) {
            DB::table('vendor_memberships')->insert(['vendor_id' => $vendor, 'user_id' => $userId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['products', 'product_variants', 'inventories', 'categories', 'orders', 'order_items', 'product_images', 'product_options', 'product_reviews'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            });
            DB::table($name)->update(['vendor_id' => $vendor]);
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
                $table->unique(['vendor_id', 'id']);
            });
        }
        foreach (['products' => ['base_sku', 'slug'], 'product_variants' => ['sku'], 'categories' => ['slug']] as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropUnique([$column]);
                    $table->unique(['vendor_id', $column]);
                }
            });
        }
        // Composite constraints prevent cross-vendor relationships even in raw SQL.
        foreach (['product_variants' => ['product_id', 'products'], 'inventories' => ['variant_id', 'product_variants'], 'order_items' => ['order_id', 'orders'], 'product_images' => ['product_id', 'products'], 'product_options' => ['product_id', 'products'], 'product_reviews' => ['product_id', 'products']] as $name => [$column, $parent]) {
            Schema::table($name, fn (Blueprint $table) => $table->foreign(['vendor_id', $column])->references(['vendor_id', 'id'])->on($parent)->restrictOnDelete());
        }
        Schema::table('order_items', fn (Blueprint $table) => $table->foreign(['vendor_id', 'variant_id'])->references(['vendor_id', 'id'])->on('product_variants')->restrictOnDelete());
        Schema::table('products', fn (Blueprint $table) => $table->foreign(['vendor_id', 'category_id'])->references(['vendor_id', 'id'])->on('categories')->restrictOnDelete());
        Schema::table('categories', fn (Blueprint $table) => $table->foreign(['vendor_id', 'parent_id'])->references(['vendor_id', 'id'])->on('categories')->restrictOnDelete());
        // Retain legacy mapping for reconciliation, but point business ownership at vendors.
        Schema::table('vendor_categories', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'legacy_user_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'vendor_categories_business_foreign')->references('id')->on('vendors')->restrictOnDelete();
        });
        DB::table('vendor_categories')->update(['vendor_id' => $vendor]);
        if (! Schema::hasColumn('roles', 'vendor_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unique(['vendor_id', 'name', 'guard_name']);
            });
        }
        DB::table('roles')->update(['vendor_id' => $vendor]);
        foreach (DB::table('roles')->where('name', 'Super Admin')->get() as $role) {
            DB::table('roles')->where('guard_name', $role->guard_name)->where('name', 'Owner')->update(['name' => 'Legacy Owner '.Str::uuid()]);
            DB::table('roles')->where('id', $role->id)->update(['name' => 'Owner']);
        }
        Schema::table('roles', fn (Blueprint $table) => $table->unique(['vendor_id', 'id']));
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $name => $key) {
            if (! Schema::hasColumn($name, 'vendor_id')) {
                Schema::create($name.'_scoped', function (Blueprint $table) use ($key) {
                    $table->unsignedBigInteger($key);
                    $table->string('model_type');
                    $table->unsignedBigInteger('model_id');
                    $table->unsignedBigInteger('vendor_id');
                    $table->primary(['vendor_id', $key, 'model_id', 'model_type']);
                    $table->foreign($key)->references('id')->on($key === 'role_id' ? 'roles' : 'permissions')->cascadeOnDelete();
                });
                DB::table($name)->orderBy('model_id')->chunk(100, function ($rows) use ($name, $vendor) {
                    foreach ($rows as $row) {
                        DB::table($name.'_scoped')->insert([...((array) $row), 'vendor_id' => $vendor]);
                    }
                });
                Schema::drop($name);
                Schema::rename($name.'_scoped', $name);
            }
        }
        Schema::table('model_has_roles', fn (Blueprint $table) => $table->foreign(['vendor_id', 'role_id'])->references(['vendor_id', 'id'])->on('roles')->restrictOnDelete());
        Schema::create('vendor_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['vendor_id', 'user_id']);
        });
        // Preserve both existing buyers and role-less customer accounts.
        $buyers = DB::table('orders')->pluck('user_id')->merge(DB::table('users')->whereNotIn('id', $staff)->pluck('id'))->unique();
        foreach ($buyers as $id) {
            DB::table('vendor_customers')->insert(['vendor_id' => $vendor, 'user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->unique(['vendor_id', 'user_id', 'idempotency_key']);
            $table->index(['vendor_id', 'created_at']);
        });
        Schema::create('vendor_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
        Schema::create('manual_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('reference');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->foreign(['vendor_id', 'order_id'])->references(['vendor_id', 'id'])->on('orders')->restrictOnDelete();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete();
        });
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_valid_quantities CHECK (stock_quantity >= 0 AND reserved_quantity >= 0 AND reserved_quantity <= stock_quantity)');
        }
        // Financial history must survive hard deletion attempts.
        foreach (['orders' => ['user_id', 'users'], 'order_items' => ['order_id', 'orders'], 'products' => ['user_id', 'users']] as $name => [$column, $parent]) {
            Schema::table($name, function (Blueprint $table) use ($column, $parent) {
                $table->dropForeign([$column]);
                $table->foreign($column)->references('id')->on($parent)->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Vendor migration is forward-only. Restore a verified pre-migration backup to revert.');
    }
};
