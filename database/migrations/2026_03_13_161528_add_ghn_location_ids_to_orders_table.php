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
            $table->integer('ghn_province_id')->nullable()->after('ghn_deliver_station_id');
            $table->integer('ghn_district_id')->nullable()->after('ghn_province_id');
            $table->string('ghn_ward_code')->nullable()->after('ghn_district_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ghn_province_id', 'ghn_district_id', 'ghn_ward_code']);
        });
    }
};
