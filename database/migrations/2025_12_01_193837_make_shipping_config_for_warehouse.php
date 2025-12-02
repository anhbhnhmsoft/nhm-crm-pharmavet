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
        Schema::create('shipping_config_for_warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('organization_id');

            $table->string('account_name')->comment('Tài khoản');
            $table->string('api_token')->comment('API Token');
            $table->string('store_id')->nullable()->comment('ID Cửa hàng GHN');

            $table->boolean('use_insurance')->default(false)->comment('Sử dụng bảo hiểm');
            $table->unsignedBigInteger('insurance_limit')->nullable()->comment('Giá trị bảo hiểm tối đa');

            $table->string('required_note')->nullable()->comment('Lựa chọn xem hàng');
            $table->string('pickup_shift')->nullable()->comment('Ca lấy hàng');

            $table->decimal('cod_failed_amount', 15, 0)->default(0)->comment('Giao hàng thất bại thu tiền');
            $table->boolean('fix_receiver_phone')->default(false)->comment('Cố định SĐT người nhận');
            $table->boolean('is_default')->default(false)->comment('Giao hàng bằng mặc định');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_config_for_warehouses');
    }
};
