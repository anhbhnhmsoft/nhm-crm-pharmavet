<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('month', 7)->index(); // YYYY-MM
            $table->decimal('kpi_amount', 15, 2)->default(0);
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->json('bonus_rules_json')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'month'], 'uniq_sale_kpi_month');
        });

        Schema::create('sale_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('kpi_target', 15, 2)->default(0);
            $table->json('warning_thresholds_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pushsale_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('rules_json')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('team_report_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('group_key', 100)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'leader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_report_scopes');
        Schema::dropIfExists('pushsale_rule_sets');
        Schema::dropIfExists('sale_levels');
        Schema::dropIfExists('sale_kpi_targets');
    }
};
