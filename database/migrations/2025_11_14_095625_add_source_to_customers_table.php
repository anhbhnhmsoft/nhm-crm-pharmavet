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
        Schema::table('customers', function (Blueprint $table) {
            $table->tinyInteger('source')->nullable()->after('assigned_staff_id')->comment('Nguồn lead: Facebook Ads, Landing Page, Website, Manual, etc.');
            $table->string('source_detail')->nullable()->after('source')->comment('Chi tiết nguồn: Tên campaign, form, etc.');
            $table->string('source_id')->nullable()->after('source_detail')->comment('ID từ nguồn bên ngoài');
            $table->text('note')->nullable()->after('source_id')->comment('Ghi chú');
            $table->string('email')->nullable()->after('phone')->comment('Email khách hàng');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['source']);

            $table->dropColumn([
                'source',
                'source_detail',
                'source_id',
                'note',
                'email',
            ]);
        });
    }
};
