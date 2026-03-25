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
        /**
         *  Cấu hình phân bổ (LeadDistributionConfig)
         * ------------------------------------------------
         */
        if (!Schema::hasTable('lead_distribution_configs')) {
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
        }

        /**
         * Khách hàng (Customer)
         * ------------------------------------------------
         */
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete()
                ->comment('Tổ chức sở hữu khách hàng');

            $table->string('username', 50)->comment('Tên khách hàng');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');
            $table->string('address', 255)->nullable()->comment('Địa chỉ');

            $table->unsignedTinyInteger('customer_type')->comment('Loại khách hàng');
            $table->tinyInteger('interaction_status')->comment('Trạng thái tương tác khách hàng');

            $table->foreignId('assigned_staff_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Nhân viên được phân công chính');

            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index(['assigned_staff_id', 'customer_type']);
            });
        }

        /**
         * Quy tắc chi tiết (LeadDistributionRule)
         * ------------------------------------------------
         */
        if (!Schema::hasTable('lead_distribution_rules')) {
            Schema::create('lead_distribution_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('config_id')
                ->constrained('lead_distribution_configs')
                ->cascadeOnDelete()
                ->comment('Cấu hình cha');

            $table->unsignedTinyInteger('customer_type')->comment('Loại khách hàng được áp dụng');
            $table->unsignedTinyInteger('staff_type')->comment('Loại nhân viên được áp dụng');
            $table->unsignedTinyInteger('distribution_method')->comment('Cơ chế chia');

            $table->unique(['distribution_method', 'customer_type', 'staff_type','config_id'], 'rule_config_unique');

            $table->timestamps();
            $table->softDeletes();
            });
        }

        /**
         * Nhân viên được phân bổ theo cấu hình (LeadDistributionStaff)
         * ------------------------------------------------
         */
        if (!Schema::hasTable('lead_distribution_staff')) {
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
            $table->unique(['config_id', 'staff_id'], 'unique_lead_distribution_staff');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Compatibility migration: keep schema from baseline init migration.
    }
};
