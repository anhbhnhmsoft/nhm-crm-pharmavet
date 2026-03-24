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
        // --- 4. Bảng Shifts (Ca làm việc) ---
        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->comment('Tên ca làm việc');
                $table->foreignId('organization_id')
                    ->constrained('organizations')
                    ->comment('Tổ chức của ca làm việc');
                $table->time('start_time')->comment('Giờ bắt đầu ca');
                $table->time('end_time')->comment('Giờ kết thúc ca');

                $table->timestamps();
                $table->softDeletes();
            });
        }

        // --- 5. Bảng User_shift (Người dùng trong ca làm việc) ---
        if (!Schema::hasTable('user_shift')) {
            Schema::create('user_shift', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->comment('Người dùng có trong ca làm việc');

                $table->foreignId('shift_id')
                    ->constrained('shifts')
                    ->comment('Ca làm việc của người dùng');

                $table->unique(['user_id', 'shift_id']);

                $table->timestamps();
                $table->softDeletes();
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
