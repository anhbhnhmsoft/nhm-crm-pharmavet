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
//        Schema::create('reconciliations', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
//            $table->date('reconciliation_date')->comment('Ngày đối soát');
//            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete()->comment('Đơn hàng liên quan (nếu đối soát theo đơn)');
//            $table->string('ghn_order_code', 100)->nullable()->comment('Mã đơn GHN');
//
//            // Chi phí từ GHN
//            $table->decimal('cod_amount', 15, 2)->default(0)->comment('Tiền COD');
//            $table->decimal('shipping_fee', 15, 2)->default(0)->comment('Phí giao hàng');
//            $table->decimal('storage_fee', 15, 2)->default(0)->comment('Phí kho');
//            $table->decimal('total_fee', 15, 2)->default(0)->comment('Tổng phí');
//
//            // Tỉ giá (cho đơn vị nước ngoài)
//            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
//            $table->decimal('converted_amount', 15, 2)->nullable()->comment('Số tiền sau khi quy đổi theo tỉ giá');
//
//            // Trạng thái
//            $table->unsignedTinyInteger('status')->default(1)->comment('1: pending, 2: confirmed, 3: cancelled');
//            $table->text('note')->nullable()->comment('Ghi chú');
//
//            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->timestamp('confirmed_at')->nullable();
//            $table->timestamps();
//            $table->softDeletes();
//
//            $table->index(['organization_id', 'reconciliation_date']);
//            $table->index('order_id');
//            $table->index('ghn_order_code');
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::dropIfExists('reconciliations');
    }
};
