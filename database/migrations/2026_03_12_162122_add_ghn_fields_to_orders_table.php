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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('ghn_cod_failed_amount', 15, 2)->nullable()->after('ghn_total_fee');
            $table->text('ghn_content')->nullable()->after('ghn_cod_failed_amount');
            $table->integer('ghn_pick_station_id')->nullable()->after('ghn_content');
            $table->integer('ghn_deliver_station_id')->nullable()->after('ghn_pick_station_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ghn_cod_failed_amount', 'ghn_content', 'ghn_pick_station_id', 'ghn_deliver_station_id']);
        });
    }
};
