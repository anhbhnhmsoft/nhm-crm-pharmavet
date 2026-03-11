<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->string('ghn_to_name')->nullable()->after('ghn_order_code');
            $table->string('ghn_to_phone')->nullable()->after('ghn_to_name');
            $table->text('ghn_to_address')->nullable()->after('ghn_to_phone');
            $table->string('ghn_status_label')->nullable()->after('ghn_to_address');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropColumn(['ghn_to_name', 'ghn_to_phone', 'ghn_to_address', 'ghn_status_label']);
        });
    }
};
