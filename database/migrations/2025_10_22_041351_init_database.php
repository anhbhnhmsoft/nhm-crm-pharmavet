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
        // --- 1. Bảng Organizations ---
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Tên tổ chức');
            $table->string('code', 20)->unique()->comment('Mã tổ chức');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');
            $table->string('address', 255)->nullable()->comment('Địa chỉ');
            $table->unsignedSmallInteger('product_field')->comment('Lĩnh vực sản phẩm (enum ProductField)');
            $table->text('description')->nullable()->comment('Mô tả');
            $table->boolean('disable')->default(false)->comment('Trạng thái vô hiệu hóa');
            $table->unsignedInteger('maximum_employees')->default(99)->comment('Số lượng tối đa thành viên');
            $table->timestamps();
            $table->softDeletes();
        });

        // --- 2. Bảng Teams ---
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Tên đội, nhóm');
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->comment('Tổ chức phòng ban thuộc về');
            $table->string('code', 20)->unique()->comment('Mã đội nhóm');
            $table->tinyInteger('type')->nullable()->comment('Loại đội nhóm');
            $table->text('description')->nullable()->comment('Mô tả');
            $table->timestamps();
            $table->softDeletes();
        });

        // --- 2. Bảng Users ---
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->comment('Tổ chức người dùng thuộc về');

            $table->string('username', 100)->unique()->comment('Tên đăng nhập');
            $table->string('password', 255)->comment('Mật khẩu hash');
            $table->string('email', 255)->nullable()->unique()->comment('Email');
            $table->string('name', 255)->comment('Họ tên');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');
            $table->unsignedSmallInteger('role')->comment('Vai trò người dùng (enum UserRole)');
            $table->unsignedSmallInteger('position')->nullable()->comment('Chức vụ người dùng (enum UserPosition)');
            $table->decimal('salary', 15, 2)->nullable()->comment('Lương người dùng');
            $table->boolean('disable')->default(false)->comment('Trạng thái vô hiệu hóa');
            $table->decimal('online_hours', 15, 2)->nullable()->comment('Tổng số giờ online');
            $table->timestamp('last_logout_at')->nullable()->comment('Thời điểm đăng xuất gần nhất');
            $table->timestamp('last_login_at')->nullable()->comment('Thời điểm đăng nhập gần nhất');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('set null')->comment('Phòng ban làm việc');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Người tạo mới');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Người cập nhật cuối cùng');

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // --- 3. Bảng User_logs ---
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->comment('Người dùng thực hiện hành động');

            $table->text('desc')->comment('Hành động thực hiện');
            $table->string('ip_address', 255)->nullable()->comment('Địa chỉ IP');

            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // --- 4. Bảng Products ---
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete()
                ->comment('Tổ chức sở hữu sản phẩm');

            $table->string('name', 255)->comment('Tên sản phẩm gốc');
            $table->string('sku', 100)->unique()->comment('Mã SKU sản phẩm');
            $table->string('unit', 50)->nullable()->comment('Đơn vị tính');
            $table->unsignedInteger('weight')->nullable()->comment('Khối lượng (gram)');
            $table->decimal('cost_price', 15, 2)->nullable()->comment('Giá nhập');
            $table->decimal('sale_price', 15, 2)->nullable()->comment('Giá bán');
            $table->string('image', 255)->nullable()->comment('Hình ảnh sản phẩm');
            $table->text('description')->nullable()->comment('Miêu tả sản phẩm');
            $table->string('barcode', 100)->nullable()->comment('Mã vạch');
            $table->string('type', 100)->nullable()->comment('Phân loại sản phẩm');
            $table->string('length', 50)->nullable()->comment('Chiều dài');
            $table->string('width', 50)->nullable()->comment('Chiều rộng');
            $table->string('height', 50)->nullable()->comment('Chiều cao');
            $table->unsignedInteger('quantity')->default(0)->comment('Số lượng sản phẩm');
            $table->unsignedTinyInteger('vat_rate')->default(0)->comment('Thuế VAT (%)');
            $table->boolean('is_business_product')->default(false)->comment('SP ngừng kinh doanh');
            $table->boolean('has_attributes')->default(false)->comment('Có thuộc tính (biến thể)');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'sku']);
        });

        // --- 5. Bảng Product Attributes ---
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('name', 100)->comment('Tên thuộc tính (VD: Màu sắc, Kích cỡ)');
            $table->string('value', 100)->comment('Giá trị thuộc tính (VD: Đỏ, XL)');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['product_id', 'name']);
        });


        // --- 6. Bảng Product_User_Assignments ---
        Schema::create('product_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Nhân viên được gán');
            $table->unsignedTinyInteger('type')
                ->comment('1: SALE, 2: CSKH, 3: MARKETING, 4: BILL_OF_LADING');
            $table->timestamps();

            $table->unique(['product_id', 'user_id', 'type']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_user_assignments');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('users');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('organizations');
    }
};
