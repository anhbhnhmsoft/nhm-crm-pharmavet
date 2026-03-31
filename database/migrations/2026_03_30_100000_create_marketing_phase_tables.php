<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facebook_event_logs')) {
            Schema::create('facebook_event_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('integration_id')->nullable()->index();
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->string('event_name', 50);
                $table->string('event_id', 120)->nullable()->index();
                $table->json('payload_json');
                $table->json('hashed_payload_json')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedInteger('retry_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('next_retry_at')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('website_lead_ingest_logs')) {
            Schema::create('website_lead_ingest_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('integration_id')->nullable()->index();
                $table->string('site_id', 100)->index();
                $table->string('request_id', 120)->nullable()->index();
                $table->string('status', 20)->default('received')->index();
                $table->json('payload_json');
                $table->json('normalized_json')->nullable();
                $table->json('error_json')->nullable();
                $table->timestamp('received_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketing_scoring_rule_sets')) {
            Schema::create('marketing_scoring_rule_sets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->string('name');
                $table->json('rules_json')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketing_budgets')) {
            Schema::create('marketing_budgets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->date('date')->index();
                $table->string('channel', 100)->index();
                $table->string('campaign', 150)->index();
                $table->decimal('budget_amount', 20, 2)->default(0);
                $table->timestamps();

                $table->unique(['organization_id', 'date', 'channel', 'campaign'], 'uq_marketing_budget_daily');
            });
        }

        if (!Schema::hasTable('marketing_spends')) {
            Schema::create('marketing_spends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->date('date')->index();
                $table->string('channel', 100)->index();
                $table->string('campaign', 150)->index();
                $table->decimal('actual_spend', 20, 2)->default(0);
                $table->decimal('fee_amount', 20, 2)->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketing_spend_attachments')) {
            Schema::create('marketing_spend_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketing_spend_id')->index();
                $table->unsignedInteger('version')->default(1);
                $table->string('file_path');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();

                $table->unique(['marketing_spend_id', 'version'], 'uq_marketing_spend_attachment_version');
            });
        }

        if (!Schema::hasTable('marketing_alert_logs')) {
            Schema::create('marketing_alert_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->string('alert_type', 50)->index();
                $table->string('severity', 30)->default('warning')->index();
                $table->string('channel', 100)->nullable()->index();
                $table->string('campaign', 150)->nullable()->index();
                $table->json('payload_json')->nullable();
                $table->timestamp('triggered_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first: keep history safe.
    }
};
