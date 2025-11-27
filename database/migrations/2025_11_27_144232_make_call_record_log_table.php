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
        Schema::create('call_record_of_telesale_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_interaction_id')->nullable()->constrained('customer_interactions')->cascadeOnDelete();
            $table->foreignId('customer_status_log_id')->nullable()->constrained('customer_status_logs')->cascadeOnDelete();
            $table->string('path_record')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_record_of_telesale_operations');
    }
};
