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
//        // --- 1. Bảng Products ---
//        Schema::create('products', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('organization_id')
//                ->constrained('organizations')
//                ->comment('Tổ chức sở hữu sản phẩm');
//
//            $table->string('name', 255)->comment('Tên sản phẩm gốc (Tên SP gốc)');
//            $table->string('sku', 100)->unique()->comment('Mã SKU sản phẩm');
//            $table->string('unit', 50)->nullable()->comment('Đơn vị tính');
//            $table->unsignedInteger('weight')->comment('Khối lượng');
//            $table->decimal('cost_price', 15, 2)->nullable()->comment('Giá nhập');
//            $table->decimal('sale_price', 15, 2)->nullable()->comment('Giá bán');
//            $table->string('barcode', 100)->nullable()->comment('Mã vạch');
//            $table->string('image', 255)->nullable()->comment('Hình ảnh sản phẩm');
//            $table->text('description')->nullable()->comment('Miêu tả sản phẩm');
//            $table->string('type', 100)->nullable()->comment('Phân loại/Phân loại-');
//            $table->string('length', 50)->nullable()->comment('Chiều dài');
//            $table->string('width', 50)->nullable()->comment('Chiều rộng');
//            $table->string('height', 50)->nullable()->comment('Chiều cao');
//            $table->unsignedInteger('quantity')->comment('Số lượng sản phẩm');
//            $table->unsignedTinyInteger('vat_rate')->default(0)->comment('Thuế VAT (%)');
//            $table->tinyInteger('type_vat')
//                ->default(1)
//                ->comment('Phân loại/Trạng thái Thuế VAT (KCT, KKKNT, Thuế suất)');
//            $table->boolean('is_business_product')->default(false)->comment('SP ngừng kinh doanh');
//            $table->boolean('has_attributes')->default(false)->comment('Có thuộc tính (biến thể)');
//            $table->timestamps();
//            $table->softDeletes();
//            $table->index(['organization_id', 'sku']);
//        });
//
//        // --- 2. Bảng Product_Attributes ---
//        Schema::create('product_attributes', function (Blueprint $table) {
//            $table->id();
//            $table->string('name', 100)->comment('Tên thuộc tính (Màu sắc, Kích cỡ)');
//            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
//            $table->string('value', 100)->comment('Giá trị (Ví dụ: Đỏ, Xanh)');
//            $table->softDeletes();
//            $table->timestamps();
//            $table->index(['product_id', 'name']);
//        });
//
//        // --- 3. Bảng Product_User_Assignments (gộp các bảng pivot cũ) ---
//        Schema::create('product_user_assignments', function (Blueprint $table) {
//            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
//            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Nhân viên được gán');
//            $table->unsignedTinyInteger('type')->comment('1:SALE, 2:CSKH, 3:MARKETING, 4:BILL_OF_LADING');
//            $table->timestamps();
//            $table->unique(['product_id', 'user_id', 'type']);
//            $table->index(['type']);
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::dropIfExists('product_user_assignments');
//        Schema::dropIfExists('product_attributes');
//        Schema::dropIfExists('products');
    }
};
