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
        if (!Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->date('rate_date')->comment('Ngày áp dụng tỉ giá');
                $table->string('from_currency', 3)->default('VND')->comment('Đơn vị tiền tệ gốc (VND)');
                $table->string('to_currency', 3)->comment('Đơn vị tiền tệ đích (USD, EUR, ...)');
                $table->decimal('rate', 15, 6)->comment('Tỉ giá quy đổi');
                $table->string('source', 50)->default('manual')->comment('Nguồn: manual (nhập tay), api (tự động từ API)');
                $table->text('note')->nullable()->comment('Ghi chú');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                // Unique constraint: mỗi tổ chức chỉ có 1 tỉ giá cho 1 ngày và 1 loại tiền tệ
                $table->unique(['organization_id', 'rate_date', 'to_currency'], 'unique_org_date_currency');
                $table->index(['organization_id', 'rate_date']);
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
