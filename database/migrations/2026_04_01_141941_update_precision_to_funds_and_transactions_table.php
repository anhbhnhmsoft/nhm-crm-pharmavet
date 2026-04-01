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
        Schema::table('funds', function (Blueprint $table) {
            $table->decimal('balance', 20, 2)->change();
        });

        Schema::table('fund_transactions', function (Blueprint $table) {
            $table->decimal('amount', 20, 2)->change();
            $table->decimal('balance_after', 20, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->decimal('balance', 8, 2)->change();
        });

        Schema::table('fund_transactions', function (Blueprint $table) {
            $table->decimal('amount', 8, 2)->change();
            $table->decimal('balance_after', 8, 2)->change();
        });
    }
};
