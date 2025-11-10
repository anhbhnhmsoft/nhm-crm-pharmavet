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
        Schema::create('shipping_configs', function (Blueprint $table) {
            $table->id()->comment('Khóa chính của bảng cấu hình GHN');

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Mã tổ chức (organization) sở hữu cấu hình GHN này');

            $table->string('account_name')
                ->comment('Tên tài khoản GHN do người dùng nhập, dùng để xác định tài khoản GHN đang kết nối');

            $table->text('api_token')
                ->encrypted()
                ->comment('API Token do GHN cấp, dùng để xác thực khi gọi API GHN; được mã hóa khi lưu');

            $table->string('default_store_id')
                ->nullable()
                ->comment('ID cửa hàng hoặc kho mặc định được chọn từ danh sách cửa hàng GHN');

            $table->boolean('use_insurance')
                ->default(false)
                ->comment('Bật/tắt sử dụng bảo hiểm cho đơn hàng GHN');

            $table->decimal('insurance_limit', 15, 2)
                ->nullable()
                ->comment('Giới hạn giá trị bảo hiểm tối đa (VNĐ) mà GHN cho phép, ví dụ 5.000.000');

            $table->tinyInteger('required_note')
                ->comment('Cho phép khách hàng xem hàng trước khi nhận: true = cho xem, false = không cho xem');

            $table->boolean('allow_cod_on_failed')
                ->default(false)
                ->comment('Cho phép thu thêm khi giao hàng thất bại: true = có, false = không');

            $table->tinyInteger('default_pickup_shift')
                ->nullable()
                ->comment('Mã ca lấy hàng mặc định từ GHN, ví dụ: sáng / chiều / tối');

            $table->timestamp('default_pickup_time')
                ->nullable()
                ->comment('Thời gian lấy hàng mặc định mong muốn');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_configs');
    }
};
