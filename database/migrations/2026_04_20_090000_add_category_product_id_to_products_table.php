<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'category_product_id')) {
                $table->foreignId('category_product_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('category_products')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'category_product_id')) {
                $table->dropConstrainedForeignId('category_product_id');
            }
        });
    }
};
