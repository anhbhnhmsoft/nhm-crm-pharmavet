<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_printed')->default(false)->after('invoice_url')
                ->comment('Đã in phiếu giao hàng / tem nhãn');
            $table->timestamp('care_updated_at')->nullable()->after('is_printed')
                ->comment('Thời điểm chăm sóc đơn gần nhất');
            $table->foreignId('care_by_id')->nullable()->after('care_updated_at')
                ->constrained('users')->nullOnDelete()
                ->comment('Nhân viên thực hiện care đơn');

            $table->index('is_printed');
            $table->index('care_updated_at');
        });

        Schema::table('reconciliations', function (Blueprint $table) {
            $table->boolean('is_internal_reconciled')->default(false)->after('note')
                ->comment('Đã đối soát nội bộ (phòng kế toán đã kiểm tra)');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['care_by_id']);
            $table->dropIndex(['is_printed']);
            $table->dropIndex(['care_updated_at']);
            $table->dropColumn(['is_printed', 'care_updated_at', 'care_by_id']);
        });

        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropColumn('is_internal_reconciled');
        });
    }
};
