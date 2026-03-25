<?php

namespace App\Database\Migrations;

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
//        // =======================================================
//        // Bảng PROVINCES (Tỉnh/Thành phố)
//        // =======================================================
//        Schema::create('provinces', function (Blueprint $table) {
//            $table->id(); // Khóa chính tự động tăng
//
//            $table->string('code', 20)->unique(); // Mã tỉnh từ API
//            $table->string('name', 100);
//            $table->string('code_name', 100);
//            $table->string('division_type', 100);
//
//            $table->json('metadata')->nullable();
//            $table->timestamps();
//        });
//
//        // =======================================================
//        // Bảng DISTRICTS (Quận/Huyện)
//        // =======================================================
//        Schema::create('districts', function (Blueprint $table) {
//            $table->id(); // Khóa chính tự động tăng
//
//            $table->string('code', 20)->unique(); // Mã quận/huyện từ API
//            $table->string('name', 100);
//            $table->string('code_name', 150);
//            $table->string('division_type', 100);
//
//            // Khóa ngoại tham chiếu đến provinces.id
//            $table->foreignId('province_id')
//                ->constrained('provinces')
//                ->onDelete('cascade');
//
//            // Cột lưu province_code để tham chiếu
//            $table->string('province_code', 20)->nullable()->index();
//
//            $table->json('metadata')->nullable();
//            $table->timestamps();
//        });
//
//        // =======================================================
//        // Bảng WARDS (Phường/Xã)
//        // =======================================================
//        Schema::create('wards', function (Blueprint $table) {
//            $table->id(); // Khóa chính tự động tăng
//
//            $table->string('code', 20)->unique(); // Mã phường/xã từ API
//            $table->string('name', 100);
//            $table->string('code_name', 150);
//            $table->string('division_type', 100);
//
//            // Khóa ngoại tham chiếu đến districts.id
//            $table->foreignId('district_id')
//                ->constrained('districts')
//                ->onDelete('cascade');
//
//            // Cột lưu district_code để tham chiếu
//            $table->string('district_code', 20)->nullable()->index();
//
//            $table->json('metadata')->nullable();
//            $table->timestamps();
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::dropIfExists('wards');
//        Schema::dropIfExists('districts');
//        Schema::dropIfExists('provinces');
    }
};
