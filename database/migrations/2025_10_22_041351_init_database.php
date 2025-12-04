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



        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->index();

            $table->string('name', 100);
            $table->string('code_name', 100);
            $table->string('division_type', 100);

            $table->json('metadata')->nullable();

            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->char('code', 5)->index();

            $table->string('name', 100);
            $table->string('code_name', 150);
            $table->string('division_type', 100);

            $table->foreignId('province_id')
                ->constrained('provinces')
                ->onDelete('cascade')
                ->comment('Tỉnh/thành phố sở hữu quận/huyện');

            $table->char('province_code', 2)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });


        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->char('code', 5)->index();
            $table->string('name', 100);
            $table->string('code_name', 150);
            $table->string('division_type', 100);

            $table->foreignId('district_id')
                ->constrained('districts')
                ->onDelete('cascade')
                ->comment('Quận/huyện sở hữu phường/xã');

            $table->char('district_code', 5)->nullable()->index();

            $table->json('metadata')->nullable();
            $table->timestamps();
        });

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

        // Bảng danh sách người nhân viên trong team

        Schema::create('user_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->timestamps();
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
            $table->tinyInteger('type_vat')->default(1)->comment('Phân loại/Trạng thái Thuế VAT (KCT, KKKNT, Thuế suất)');
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
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete()
                ->comment('ID tổ chức');
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

        // --- 9. Bảng Shipping_Configs ~ Cấu hình giao hàng ---
        // (Moved to end of file to ensure warehouses table exists)

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
            $table->date('birthday')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->unsignedInteger('province_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->unsignedInteger('ward_id')->nullable();
            $table->string('shipping_address', 255)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->string('address', 255)->nullable()->comment('Địa chỉ');

            $table->unsignedTinyInteger('customer_type')->comment('Loại khách hàng');
            $table->tinyInteger('interaction_status')->default(1)->comment('Trạng thái tương tác khách hàng');

            $table->foreignId('assigned_staff_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Nhân viên được phân công chính');

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete()
                ->comment('Sản phẩm quan tâm');

            $table->text('note_temp')->nullable()->comment('Ghi chú tạm thời');

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
            $table->index('interaction_status');
            $table->index('next_action_at');
            $table->index(['organization_id', 'customer_type']);
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

        /**
         * 14. Nguồn phân bổ data (Integration)
         * ------------------------------------------------ 
         */
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

        /**
         * 15. Nguồn phân bổ data (Integration Entity)
         * ------------------------------------------------ 
         */
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

        /**
         * 16. Token truy cập thực thể phân bổ data
         */

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

        /**
         * 17. Orders đơn hàng
         */

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('code', 50)->unique()->comment('Mã đơn hàng');
            $table->unsignedTinyInteger('status')->nullable()->comment('pending, confirmed, shipping, completed, cancelled');

            // Financials
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);

            // Shipping
            $table->string('shipping_method', 50)->nullable(); // ghn, ghtk
            $table->string('shipping_address', 255)->nullable();
            $table->unsignedInteger('province_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->unsignedInteger('ward_id')->nullable();

            $table->text('note')->nullable();
            $table->unsignedTinyInteger('required_note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * 18. OrderItem ~ Lưu trữ thành phần đơn hàng
         */

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->integer('quantity')->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->timestamps();
        });

        /**
         * 19. Customer Iteractions ~ Tracking lịch sử tương tác với khách hàng
         */

        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('type')->comment('call, sms, email, note, meeting');
            $table->unsignedTinyInteger('direction')->nullable()->comment('inbound, outbound');
            $table->unsignedTinyInteger('status')->nullable()->comment('completed, missed, failed, etc.');

            $table->integer('duration')->nullable()->comment('Thời lượng cuộc gọi (giây)');
            $table->text('content')->nullable()->comment('Nội dung tin nhắn/ghi chú');
            $table->json('metadata')->nullable()->comment('Dữ liệu bổ sung: recording_url, attachments, etc.');

            $table->timestamp('interacted_at')->useCurrent()->comment('Thời điểm tương tác');
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index('interacted_at');
        });

        /**
         * 20. Order Status Log ~ Bảng lưu trạng thái đơn hàng
         */
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('from_status')->nullable();
            $table->unsignedTinyInteger('to_status');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        /**
         * 21. Customer Status Log ~ Bảng lưu trạng thái khách hàng
         */
        Schema::create('customer_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('from_status')->nullable();
            $table->unsignedTinyInteger('to_status');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        /**
         * 22. Black List ~ Danh sách đen
         */
        Schema::create('black_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();
            $table->tinyInteger('reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        // --- 23. Shipping Config For Warehouses ~ Cấu hình giao hàng cho kho ---
        // (Moved to end of file to ensure warehouses table exists)

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

        Schema::create('inventory_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique()->comment('Mã phiếu');
            $table->unsignedTinyInteger('type')->comment('1: Import, 2: Export, 3: Transfer, 4: Cancel Export');
            $table->unsignedTinyInteger('status')->default(1)->comment('1: Draft, 2: Completed, 3: Cancelled');

            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete()->comment('Kho thực hiện');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Kho nguồn (cho chuyển kho)');
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Kho đích (cho chuyển kho)');

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Inventory Ticket Details
        Schema::create('inventory_ticket_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->comment('Số lượng');
            $table->integer('current_quantity')->nullable()->comment('Số lượng tồn tại thời điểm tạo phiếu');
            $table->timestamps();
        });

        // 3. Inventory Ticket Logs
        Schema::create('inventory_ticket_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('note', 255)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Product Warehouse (Pivot)
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0)->comment('Số lượng tồn kho');
            $table->integer('pending_quantity')->default(0)->comment('Số lượng chờ xuất');
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        Schema::create('shipping_configs', function (Blueprint $table) {
            $table->id()->comment('Khóa chính của bảng cấu hình GHN');

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Mã tổ chức (organization) sở hữu cấu hình GHN này');

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete()
                ->comment('Kho mặc định (nếu có)');

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

            $table->string('required_note', 20)
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

        Schema::create('shipping_config_for_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

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
        Schema::dropIfExists('product_warehouse');
        Schema::dropIfExists('inventory_ticket_logs');
        Schema::dropIfExists('inventory_ticket_details');
        Schema::dropIfExists('inventory_tickets');
        Schema::dropIfExists('warehouse_delivery_provinces');
        Schema::dropIfExists('warehouses');

        Schema::dropIfExists('black_list');
        Schema::dropIfExists('customer_interactions');
        Schema::dropIfExists('customer_status_logs');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_items');

        Schema::dropIfExists('product_user_assignments');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('combo_product');
        Schema::dropIfExists('lead_distribution_staff');
        Schema::dropIfExists('lead_distribution_rules');

        Schema::dropIfExists('integration_tokens');
        Schema::dropIfExists('integration_entities');

        Schema::dropIfExists('orders');
        Schema::dropIfExists('combos');
        Schema::dropIfExists('shipping_configs');

        Schema::dropIfExists('customers');
        Schema::dropIfExists('lead_distribution_configs');
        Schema::dropIfExists('integrations');

        Schema::dropIfExists('products');
        Schema::dropIfExists('user_shift');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_team');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('organizations');

        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
