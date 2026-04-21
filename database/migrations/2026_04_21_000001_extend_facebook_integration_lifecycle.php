<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_entities', function (Blueprint $table) {
            $table->foreignId('approved_by')
                ->nullable()
                ->after('connected_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->timestamp('webhook_subscribed_at')->nullable()->after('rejected_at');
            $table->timestamp('last_lead_received_at')->nullable()->after('webhook_subscribed_at');
            $table->text('status_reason')->nullable()->after('last_lead_received_at');
            $table->timestamp('disconnected_at')->nullable()->after('status_reason');

            $table->index(['type', 'external_id', 'status'], 'integration_entities_type_external_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('integration_entities', function (Blueprint $table) {
            $table->dropIndex('integration_entities_type_external_status_idx');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'rejected_at',
                'webhook_subscribed_at',
                'last_lead_received_at',
                'status_reason',
                'disconnected_at',
            ]);
        });
    }
};
