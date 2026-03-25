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
//        // 1. Bảng customer_interactions - Lịch sử tương tác
//        Schema::create('customer_interactions', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
//            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Nhân viên thực hiện');
//
//            $table->unsignedTinyInteger('type')->comment('call, sms, email, note, meeting');
//            $table->unsignedTinyInteger('direction')->nullable()->comment('inbound, outbound');
//            $table->unsignedTinyInteger('status')->nullable()->comment('completed, missed, failed, etc.');
//
//            $table->integer('duration')->nullable()->comment('Thời lượng cuộc gọi (giây)');
//            $table->text('content')->nullable()->comment('Nội dung tin nhắn/ghi chú');
//            $table->json('metadata')->nullable()->comment('Dữ liệu bổ sung: recording_url, attachments, etc.');
//            $table->foreignId('interaction_id')->nullable()->constrained('interactions')->nullOnDelete()->comment('Nguồn tương tác đổ data về');
//
//            $table->timestamp('interacted_at')->useCurrent()->comment('Thời điểm tương tác');
//            $table->timestamps();
//
//            $table->index(['customer_id', 'type']);
//            $table->index('interacted_at');
//        });
//
//        // 2. Bảng order_status_logs - Tracking thay đổi trạng thái đơn hàng
//        Schema::create('order_status_logs', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
//            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
//
//            $table->unsignedTinyInteger('from_status')->nullable();
//            $table->unsignedTinyInteger('to_status');
//            $table->text('note')->nullable();
//
//            $table->timestamps();
//
//            $table->index(['order_id', 'created_at']);
//        });
//
//        // 3. Bảng customer_status_logs - Tracking thay đổi trạng thái khách hàng
//        Schema::create('customer_status_logs', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
//            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
//
//            $table->unsignedTinyInteger('from_status')->nullable();
//            $table->unsignedTinyInteger('to_status');
//            $table->text('note')->nullable();
//            $table->tinyInteger('reason')->nullable();
//            $table->timestamps();
//
//            $table->index(['customer_id', 'created_at']);
//        });
//
//        // 4. Bảng black_list - Danh sách đen
//        Schema::create('black_list', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
//            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
//
//            $table->text('note')->nullable();
//            $table->tinyInteger('reason')->nullable();
//            $table->timestamps();
//
//            $table->index(['customer_id', 'created_at']);
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::dropIfExists('order_status_logs');
//        Schema::dropIfExists('customer_interactions');
//        Schema::dropIfExists('customer_status_logs');
//        Schema::dropIfExists('black_list');
    }
};
