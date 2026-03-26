<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('invoice_status')->default(1)->comment('1: Chưa xuất, 2: Đã xuất, 3: Đã hủy');
            $table->string('invoice_code', 100)->nullable();
            $table->string('invoice_url', 255)->nullable();
            $table->timestamp('invoice_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
