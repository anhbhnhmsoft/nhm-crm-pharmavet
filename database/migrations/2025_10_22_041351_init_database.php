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
        // Tạo bảng provinces để lưu trữ thông tin về các tỉnh thành
        Schema::create('provinces', function (Blueprint $table) {
            ;
            $table->id();
            $table->comment('Bảng provinces lưu trữ các tỉnh thành');
            $table->string('name')->comment('Tên');
            $table->string('code')->unique()->comment('Mã');
            $table->string('division_type')->nullable()->comment('Cấp hành chính');
            $table->timestamps();
        });

        // Tạo bảng districts để lưu trữ thông tin về các quận huyện
        Schema::create('districts', function (Blueprint $table) {
            ;
            $table->id();
            $table->comment('Bảng districts lưu trữ các quận huyện');
            $table->string('name')->comment('Tên');
            $table->string('code')->unique()->comment('Mã');
            $table->string('division_type')->nullable()->comment('Cấp hành chính');
            $table->string('province_code');
            $table->foreign('province_code')->references('code')->on('provinces')->cascadeOnDelete();
            $table->timestamps();
        });

        // Tạo bảng districts để lưu trữ thông tin về các phường xã
        Schema::create('wards', function (Blueprint $table) {
            ;
            $table->id();
            $table->comment('Bảng ward lưu trữ các phường xã');
            $table->string('name')->comment('Tên');
            $table->string('code')->unique()->comment('Mã');
            $table->string('division_type')->nullable()->comment('Cấp hành chính');

            // Khóa ngoại nối bằng code
            $table->string('district_code');
            $table->foreign('district_code')->references('code')->on('districts')->cascadeOnDelete();
            $table->timestamps();
        });

        // Tạo bảng Organizations để lưu trữ thông tin về các đơn vị
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->comment('Bảng organizations lưu trữ các đơn vị');

            $table->string('name');
            $table->string('code', 20)->unique()->comment('Mã đơn vị');
            $table->text('description')->nullable()->comment('Mô tả về tổ chức');
            $table->string('address')->nullable()->comment('Địa chỉ tổ chức');
            $table->string('phone')->nullable()->comment('Số điện thoại liên hệ');
            $table->smallInteger('product_field')->nullable()->comment('Lĩnh vực sản phẩm chính, enum ProductField');
            $table->string('province_code')->nullable()->comment('Mã tỉnh thành');
            $table->string('district_code')->nullable()->comment('Mã quận huyện');
            $table->string('ward_code')->nullable()->comment('Mã phường xã');
            $table->boolean('disable')->default(false)->comment('Trạng thái vô hiệu hóa');

            $table->foreign('province_code')->references('code')->on('provinces')->cascadeOnDelete();
            $table->foreign('district_code')->references('code')->on('districts')->cascadeOnDelete();
            $table->foreign('ward_code')->references('code')->on('wards')->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên nhóm');
            $table->foreignId('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('code')->unique()->comment('Mã nhóm');
            $table->text('description')->nullable()->comment('Mô tả về nhóm');
            $table->smallInteger('type')->comment('Loại nhóm, enum TeamType');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Tạo bảng Users để lưu trữ thông tin đăng nhập của người user đăng nhập
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('organization_code')
                ->comment('Mã tổ chức');
            $table->foreign('organization_code')
                ->references('code')
                ->on('organizations')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->nullable()
                ->references('id')
                ->on('teams')
                ->onDelete('set null');
            $table->string('name');
            $table->string('username')->comment('Tên đăng nhập');
            $table->unique(['organization_code', 'username']);
            $table->string('password');
            $table->boolean('disable')->default(false)->comment('Trạng thái vô hiệu hóa');
            $table->smallInteger('role')->comment('Vai trò, enum UserRole');
            $table->smallInteger('position')->comment('Vị trí, enum UserPosition');
            $table->string('email')->nullable()->comment('Email');
            $table->string('phone')->nullable()->comment('Số điện thoại');
            $table->string('address')->nullable()->comment('Địa chỉ');
            $table->string('avatar')->nullable()->comment('URL hình ảnh');
            $table->string('lang')->default('vi')->comment('Ngôn ngữ, enum Lang');
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->comment('Bảng user_logs lưu trữ các log liên quan đến user');
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->smallInteger('type')->comment('Loại log, enum UserLogType');
            $table->string('detail')->nullable()->comment('Nội dung log');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('users');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
