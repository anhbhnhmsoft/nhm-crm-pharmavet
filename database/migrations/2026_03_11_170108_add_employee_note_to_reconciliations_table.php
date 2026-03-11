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
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->text('ghn_employee_note')->nullable()->after('ghn_required_note');
            $table->decimal('ghn_cod_failed_amount', 15, 2)->default(0)->after('ghn_employee_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropColumn(['ghn_employee_note', 'ghn_cod_failed_amount']);
        });
    }
};
