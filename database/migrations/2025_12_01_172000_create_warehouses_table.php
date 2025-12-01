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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('Tên kho');
            $table->string('code')->comment('Mã kho');
            $table->foreignId('province_id')->constrained()->comment('Tỉnh/TP');
            $table->foreignId('district_id')->constrained()->comment('Quận/Huyện');
            $table->foreignId('ward_id')->constrained()->comment('Phường/Xã');
            $table->string('address')->comment('Địa chỉ chi tiết');
            $table->string('phone')->comment('Số điện thoại kho');
            $table->text('note')->nullable()->comment('Ghi chú');
            $table->integer('order')->default(0)->comment('Thứ tự');
            $table->foreignId('manager_id')->nullable()->constrained('users')->comment('Quản kho');
            $table->string('manager_phone')->nullable()->comment('Số ĐT quản kho');
            $table->string('sender_name')->nullable()->comment('Đăng đơn người gửi');
            $table->text('sender_info')->nullable()->comment('In đơn người gửi');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->comment('Người tạo');
            $table->foreignId('updated_by')->nullable()->constrained('users')->comment('Người cập nhật');
            $table->softDeletes();
            $table->timestamps();

            // Unique code per organization
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('warehouse_delivery_provinces', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->primary(['warehouse_id', 'province_id']);
        });

        // Add warehouse_id to shipping_configs
        Schema::table('shipping_configs', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_configs', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
        Schema::dropIfExists('warehouse_delivery_provinces');
        Schema::dropIfExists('warehouses');
    }
};
