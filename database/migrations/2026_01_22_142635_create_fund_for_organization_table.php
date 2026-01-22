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
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 8, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('type');
            $table->string('transaction_code')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('balance_after', 8, 2);
            $table->decimal('amount', 8, 2);
            $table->string('description')->nullable();
            $table->tinyInteger('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('fund_transactions');
        Schema::dropIfExists('funds');
    }
};
