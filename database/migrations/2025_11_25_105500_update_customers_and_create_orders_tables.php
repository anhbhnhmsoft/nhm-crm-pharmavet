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
        // 1. Update customers table
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'birthday')) {
                    $table->date('birthday')->nullable();
                }
                if (!Schema::hasColumn('customers', 'next_action_at')) {
                    $table->timestamp('next_action_at')->nullable()->comment('Hẹn lịch gọi lại');
                }
                if (!Schema::hasColumn('customers', 'product_id')) {
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                }

                // Location info
                if (!Schema::hasColumn('customers', 'province_id')) {
                    $table->unsignedInteger('province_id')->nullable();
                }
                if (!Schema::hasColumn('customers', 'district_id')) {
                    $table->unsignedInteger('district_id')->nullable();
                }
                if (!Schema::hasColumn('customers', 'ward_id')) {
                    $table->unsignedInteger('ward_id')->nullable();
                }
                if (!Schema::hasColumn('customers', 'shipping_address')) {
                    $table->string('shipping_address', 255)->nullable()->comment('Địa chỉ giao hàng chi tiết');
                }
                if (!Schema::hasColumn('customers', 'avatar')) {
                    $table->string('avatar', 255)->nullable();
                }
                if (!Schema::hasColumn('customers', 'note_temp')) {
                    $table->text('note_temp')->nullable()->comment('Ghi chú tạm');
                }
            });
        }

        // 2. Create orders table
        if (!Schema::hasTable('orders')) {
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
                $table->decimal('deposit', 15, 2)->default(0)->comment('Tiền đặt cọc');
                $table->decimal('cod_fee', 15, 2)->default(0)->comment('Phí dịch vụ COD');
                $table->decimal('ck1', 5, 2)->default(0)->comment('Chiết khấu 1 (%)');
                $table->decimal('ck2', 5, 2)->default(0)->comment('Chiết khấu 2 (%)');
                $table->integer('gift_quantity')->default(0)->comment('Số lượng quà tặng');

                // Shipping
                $table->string('shipping_method', 50)->nullable(); // ghn, ghtk
                $table->string('shipping_address', 255)->nullable();
                $table->unsignedInteger('province_id')->nullable();
                $table->unsignedInteger('district_id')->nullable();
                $table->unsignedInteger('ward_id')->nullable();

                $table->text('note')->nullable();
                $table->string('ghn_required_note', 50)->nullable()->comment('Lưu ý xem hàng GHN');

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Create order_items table
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

                $table->integer('quantity')->default(1);
                $table->decimal('price', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);

                $table->timestamps();
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
