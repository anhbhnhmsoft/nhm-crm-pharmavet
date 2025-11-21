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

        // --- 4. Bảng Shifts (Ca làm việc) ---
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Tên ca làm việc');
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->comment('Tổ chức của ca làm việc');
            $table->time('start_time')->comment('Giờ bắt đầu ca');
            $table->time('end_time')->comment('Giờ kết thúc ca');

            $table->timestamps();
            $table->softDeletes();
        });

        // --- 5. Bảng User_shift (Người dùng trong ca làm việc) ---
        Schema::create('user_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->comment('Người dùng có trong ca làm việc');

            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->comment('Ca làm việc của người dùng');

            $table->unique(['user_id', 'shift_id']);

            $table->timestamps();
            $table->softDeletes();
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

        // --- 7. Bảng Combos ---
        Schema::create('combos', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique()->index()->comment('Mã combo duy nhất');
            $table->string('name')->comment('Tên combo');
            $table->unsignedInteger('total_product')->default(0)->comment('Tổng số lượng sản phẩm trong combo');
            $table->decimal('total_cost', 15, 2)->default(0)->comment('Tổng giá gốc (giá nhập) của các sản phẩm');
            $table->decimal('total_combo_price', 15, 2)->default(0)->comment('Giá bán đã chiết khấu của combo');
            $table->tinyInteger('status')->comment('Trạng thái của combo (vd: Hoạt động, Tạm dừng)');
            $table->timestamp('start_date')->nullable()->comment('Ngày bắt đầu áp dụng combo');
            $table->timestamp('end_date')->nullable()->comment('Ngày kết thúc áp dụng combo');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('ID người tạo combo');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('ID người cập nhật combo gần nhất');

            $table->softDeletes();
            $table->timestamps();
        });

        // --- 8. Bảng Combo_Products ---
        Schema::create('combo_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')
                ->constrained('combos')
                ->cascadeOnDelete()
                ->comment('ID của Combo');

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->comment('ID của Sản phẩm');

            $table->unsignedInteger('quantity')->default(1)->comment('Số lượng của sản phẩm này trong combo');
            $table->decimal('price', 15, 2)->default(0)->comment('Giá bán lẻ của sản phẩm này (dùng để tính giá combo)');
            $table->timestamps();
            $table->unique(['combo_id', 'product_id'], 'combo_product_unique');
        });

        // --- 9. Bảng Shipping_Configs ---
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

        /**
         *  10 . Cấu hình phân bổ (LeadDistributionConfig)
         * ------------------------------------------------
         */
        Schema::create('lead_distribution_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete()
                ->comment('Tổ chức sở hữu cấu hình');

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete()
                ->comment('Sản phẩm áp dụng (NULL = tất cả)');

            $table->string('name', 255)->comment('Tên cấu hình');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người tạo');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Người cập nhật');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id']);
        });

        /**
         * 11. Khách hàng (Customer)
         * ------------------------------------------------
         */
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete()
                ->comment('Tổ chức sở hữu khách hàng');

            $table->string('username', 50)->comment('Tên khách hàng');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');
            $table->string('email')->nullable()->comment('Email khách hàng');
            $table->string('address', 255)->nullable()->comment('Địa chỉ');

            $table->unsignedTinyInteger('customer_type')->comment('Loại khách hàng');

            $table->foreignId('assigned_staff_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Nhân viên được phân công chính');

            // Thông tin nguồn dữ liệu
            $table->string('source', 100)->nullable()->comment('Nguồn lead: Facebook Ads, Landing Page, Website, Manual, etc.');
            $table->string('source_detail')->nullable()->comment('Chi tiết nguồn: Tên campaign, form, etc.');
            $table->string('source_id')->nullable()->comment('ID từ nguồn bên ngoài');
            $table->text('note')->nullable()->comment('Ghi chú');

            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index(['assigned_staff_id', 'customer_type']);
            $table->index('source');
        });

        /**
         * 12. Quy tắc chi tiết (LeadDistributionRule)
         * ------------------------------------------------
         */
        Schema::create('lead_distribution_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('config_id')
                ->constrained('lead_distribution_configs')
                ->cascadeOnDelete()
                ->comment('Cấu hình cha');

            $table->unsignedTinyInteger('customer_type')->comment('Loại khách hàng được áp dụng');
            $table->unsignedTinyInteger('staff_type')->comment('Loại nhân viên được áp dụng');
            $table->unsignedTinyInteger('distribution_method')->comment('Cơ chế chia');

            $table->unique(['distribution_method', 'customer_type', 'staff_type'], 'rule_config_unique');

            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * 13. Nhân viên được phân bổ theo cấu hình (LeadDistributionStaff)
         * ------------------------------------------------
         */
        Schema::create('lead_distribution_staff', function (Blueprint $table) {
            $table->id();

            $table->foreignId('config_id')
                ->constrained('lead_distribution_configs')
                ->cascadeOnDelete()
                ->comment('Cấu hình phân bổ');

            $table->foreignId('staff_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Nhân viên được phân bổ');

            $table->integer('weight')->default(1)->comment('Trọng số phân phối');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['config_id', 'staff_id']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();

            // Mỗi tổ chức có nhiều integration
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name'); // Tên cấu hình hiển thị
            $table->unsignedTinyInteger('status')->default(0); // 0=pending,1=connected,2=expired,3=error
            $table->text('status_message')->nullable();
            $table->timestamp('last_sync_at')->nullable();

            // Cấu hình chung (field mapping, webhook settings, meta config…)
            $table->json('config')->nullable();
            $table->json('field_mapping')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id']);

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('integration_entities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('type');
            // Ví dụ: 1=PAGE_META, mở rộng bằng enum IntegrationEntityType

            $table->string('external_id', 100);
            $table->string('name')->nullable();

            $table->json('metadata')->nullable(); // email, timezone, permissions, picture…

            $table->unsignedTinyInteger('status')->default(1); // active, inactive…

            $table->timestamp('connected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['integration_id', 'type']);
            $table->index('external_id');
        });

        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->cascadeOnDelete();

            // Token có thể liên quan đến 1 entity nào đó (Page/BMA/AdAccount)
            $table->foreignId('entity_id')
                ->nullable()
                ->constrained('integration_entities')
                ->nullOnDelete();

            $table->string('type', 50);
            // user_token, long_lived_user_token, business_token, page_token, webhook_secret…

            $table->text('token'); // encrypted
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedTinyInteger('status')->default(1); // active, expired

            $table->timestamps();

            $table->index(['integration_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('shipping_configs');
        Schema::dropIfExists('combo_product');
        Schema::dropIfExists('combos');
        Schema::dropIfExists('product_user_assignments');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('user_shift');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('lead_distribution_staff');
        Schema::dropIfExists('lead_distribution_rules');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('lead_distribution_configs');
        // Schema::dropIfExists('wards');
        // Schema::dropIfExists('districts');
        // Schema::dropIfExists('provinces');
    }
};
