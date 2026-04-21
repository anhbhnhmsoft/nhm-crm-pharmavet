<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('integration_entities')->nullOnDelete();
            $table->string('page_id', 100);
            $table->string('leadgen_id', 100)->unique();
            $table->string('form_id', 100)->nullable();
            $table->json('payload_json')->nullable();
            $table->json('normalized_payload_json')->nullable();
            $table->string('status', 50)->default('queued');
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['integration_id', 'entity_id']);
            $table->index(['page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_leads');
    }
};
