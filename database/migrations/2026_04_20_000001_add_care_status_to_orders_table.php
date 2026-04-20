<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('care_status')
                ->nullable()
                ->after('care_by_id')
                ->comment('Trạng thái care đơn');

            $table->index('care_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['care_status']);
            $table->dropColumn('care_status');
        });
    }
};
