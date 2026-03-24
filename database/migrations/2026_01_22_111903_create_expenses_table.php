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
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->date('expense_date')->comment('Ngày phát sinh chi phí');
                $table->unsignedTinyInteger('category')->comment('Loại chi phí (enum ExpenseCategory)');
                $table->string('description', 500)->comment('Mô tả chi phí');
                $table->decimal('amount', 15, 2)->comment('Số tiền');

                // Liên kết với đơn hàng (nếu là chi phí giao hàng tự động)
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('reconciliation_id')->nullable()->constrained('reconciliations')->nullOnDelete();

                $table->text('note')->nullable()->comment('Ghi chú');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['organization_id', 'expense_date']);
                $table->index('category');
                $table->index('order_id');
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
