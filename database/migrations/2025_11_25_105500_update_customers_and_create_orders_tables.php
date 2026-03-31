<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update orders table (Add missing fields not in init_database)
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cod_fee', 15, 2)->default(0)->after('deposit')->comment('Phí dịch vụ COD');
            $table->decimal('ck1', 5, 2)->default(0)->after('cod_fee')->comment('Chiết khấu 1 (%)');
            $table->decimal('ck2', 5, 2)->default(0)->after('ck1')->comment('Chiết khấu 2 (%)');
            $table->integer('gift_quantity')->default(0)->after('ck2')->comment('Số lượng quà tặng');
            $table->string('ghn_required_note', 50)->nullable()->after('note')->comment('Lưu ý xem hàng GHN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cod_fee',
                'ck1',
                'ck2',
                'gift_quantity',
                'ghn_required_note'
            ]);
        });
    }
};
