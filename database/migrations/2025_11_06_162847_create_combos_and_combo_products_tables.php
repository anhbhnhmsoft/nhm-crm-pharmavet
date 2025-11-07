<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique()->index()->comment('Mã combo duy nhất');
            $table->string('name')->comment('Tên combo');
            $table->unsignedInteger('total_product')->default(0)->comment('Tổng số lượng sản phẩm trong combo');
            $table->decimal('total_cost', 15, 2)->default(0)->comment('Tổng giá gốc (giá nhập) của các sản phẩm');
            $table->decimal('total_combo_price', 15, 2)->default(0)->comment('Giá bán đã chiết khấu của combo');
            $table->tinyInteger('status')->nullable()->comment('Trạng thái của combo (vd: Hoạt động, Tạm dừng)');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_product');
        Schema::dropIfExists('combos');
    }
};
