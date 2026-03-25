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
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'source')) {
                $table->tinyInteger('source')->nullable()->comment('Nguồn lead: Facebook Ads, Landing Page, Website, Manual, etc.');
                $table->index('source');
            }
            if (!Schema::hasColumn('customers', 'source_detail')) {
                $table->string('source_detail')->nullable()->comment('Chi tiết nguồn: Tên campaign, form, etc.');
            }
            if (!Schema::hasColumn('customers', 'source_id')) {
                $table->string('source_id')->nullable()->comment('ID từ nguồn bên ngoài');
            }
            if (!Schema::hasColumn('customers', 'note')) {
                $table->text('note')->nullable()->comment('Ghi chú');
            }
            if (!Schema::hasColumn('customers', 'email')) {
                $table->string('email')->nullable()->comment('Email khách hàng');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Compatibility migration: keep schema from baseline init migration.
    }
};
