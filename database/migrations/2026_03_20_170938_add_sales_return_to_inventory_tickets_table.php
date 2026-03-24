<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_tickets', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('type')->constrained('orders')->nullOnDelete()->comment('Đơn hàng liên quan (cho Nhập hoàn)');
            $table->boolean('is_sales_return')->default(false)->after('order_id')->comment('Đánh dấu là phiếu Nhập hoàn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_tickets', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id', 'is_sales_return']);
        });
    }
};
