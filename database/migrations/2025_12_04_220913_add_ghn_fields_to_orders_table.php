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
            // GHN Order tracking fields
            $table->string('ghn_order_code', 100)->nullable()->after('shipping_provider_code')->comment('Mã đơn hàng từ GHN');
            $table->timestamp('ghn_expected_delivery_time')->nullable()->after('ghn_order_code')->comment('Thời gian giao hàng dự kiến');
            $table->integer('ghn_service_type_id')->nullable()->after('ghn_expected_delivery_time')->comment('Loại dịch vụ GHN');
            $table->string('ghn_service_name', 100)->nullable()->after('ghn_service_type_id')->comment('Tên dịch vụ GHN');
            $table->integer('ghn_payment_type_id')->nullable()->after('ghn_service_name')->comment('Hình thức thanh toán GHN');
            $table->decimal('ghn_total_fee', 15, 2)->nullable()->after('ghn_payment_type_id')->comment('Tổng phí ship GHN');
            $table->text('ghn_response')->nullable()->after('ghn_total_fee')->comment('Response từ GHN API');
            $table->string('ghn_status', 50)->nullable()->after('ghn_response')->comment('Trạng thái đơn hàng trên GHN');
            $table->timestamp('ghn_posted_at')->nullable()->after('ghn_status')->comment('Thời gian đăng đơn lên GHN');
            $table->timestamp('ghn_cancelled_at')->nullable()->after('ghn_posted_at')->comment('Thời gian hủy đơn trên GHN');

            // Additional shipping info
            $table->integer('weight')->nullable()->after('ghn_cancelled_at')->comment('Khối lượng (gram)');
            $table->integer('length')->nullable()->after('weight')->comment('Chiều dài (cm)');
            $table->integer('width')->nullable()->after('length')->comment('Chiều rộng (cm)');
            $table->integer('height')->nullable()->after('width')->comment('Chiều cao (cm)');
            $table->string('insurance_value', 50)->nullable()->after('height')->comment('Giá trị bảo hiểm');
            $table->string('coupon', 50)->nullable()->after('insurance_value')->comment('Mã giảm giá');
            $table->decimal('amount_recived_from_customer', 15, 2)->nullable()->after('warehouse_id')->comment('Tiền nhận từ khách hàng');

            $table->string('provider_shipping', 50)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('shipping_provider_code', 100)->nullable();
            $table->decimal('deposit', 15, 2)->default(0);
            $table->decimal('amout_support_fee', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ghn_order_code',
                'ghn_expected_delivery_time',
                'ghn_service_type_id',
                'ghn_service_name',
                'ghn_payment_type_id',
                'ghn_total_fee',
                'ghn_response',
                'ghn_status',
                'ghn_posted_at',
                'ghn_cancelled_at',
                'weight',
                'length',
                'width',
                'height',
                'insurance_value',
                'coupon',
                'amount_recived_from_customer',
                'provider_shipping',
                'warehouse_id',
                'shipping_provider_code',
                'deposit',
                'amout_support_fee',
            ]);
        });
    }
};
