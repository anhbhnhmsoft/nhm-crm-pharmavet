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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('debt_provision_amount', 15, 2)->default(0)->comment('Số tiền dự phòng nợ khó đòi');
            $table->boolean('is_written_off')->default(false)->comment('Trạng thái xóa nợ');
            $table->timestamp('write_off_at')->nullable();
            $table->foreignId('write_off_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['write_off_by']);
            $table->dropColumn(['debt_provision_amount', 'is_written_off', 'write_off_at', 'write_off_by']);
        });
    }
};
