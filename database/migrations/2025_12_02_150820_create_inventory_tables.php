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
//        // 1. Inventory Tickets
//        Schema::create('inventory_tickets', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
//            $table->string('code')->unique()->comment('Mã phiếu');
//            $table->unsignedTinyInteger('type')->comment('1: Import, 2: Export, 3: Transfer, 4: Cancel Export');
//            $table->unsignedTinyInteger('status')->default(1)->comment('1: Draft, 2: Completed, 3: Cancelled');
//
//            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete()->comment('Kho thực hiện');
//            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Kho nguồn (cho chuyển kho)');
//            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Kho đích (cho chuyển kho)');
//
//            $table->text('note')->nullable();
//
//            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->timestamp('approved_at')->nullable();
//
//            $table->timestamps();
//            $table->softDeletes();
//        });

//        // 2. Inventory Ticket Details
//        Schema::create('inventory_ticket_details', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('inventory_ticket_id')->constrained()->cascadeOnDelete();
//            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
//            $table->integer('quantity')->comment('Số lượng');
//            $table->integer('current_quantity')->nullable()->comment('Số lượng tồn tại thời điểm tạo phiếu');
//            $table->timestamps();
//        });

//        // 3. Product Warehouse (Pivot)
//        Schema::create('product_warehouse', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
//            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
//            $table->integer('quantity')->default(0)->comment('Số lượng tồn kho');
//            $table->integer('pending_quantity')->default(0)->comment('Số lượng chờ xuất');
//            $table->timestamps();
//
//            $table->unique(['product_id', 'warehouse_id']);
//        });
//
//        Schema::create('inventory_ticket_logs', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('inventory_ticket_id')->constrained()->cascadeOnDelete();
//            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
//            $table->string('note', 255)->nullable();
//            $table->string('reason', 255)->nullable();
//            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
//            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
//            $table->timestamps();
//            $table->softDeletes();
//        });
    }

    public function down(): void
    {
//        Schema::dropIfExists('inventory_ticket_logs');
//        Schema::dropIfExists('product_warehouse');
//        Schema::dropIfExists('inventory_ticket_details');
//        Schema::dropIfExists('inventory_tickets');
    }
};
