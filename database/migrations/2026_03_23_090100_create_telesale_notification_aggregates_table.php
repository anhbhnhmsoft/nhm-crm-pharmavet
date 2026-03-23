<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telesale_notification_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('duplicate_hash', 191)->index();
            $table->unsignedInteger('lead_count')->default(1);
            $table->foreignId('last_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'duplicate_hash'], 'uniq_org_duplicate_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telesale_notification_aggregates');
    }
};
