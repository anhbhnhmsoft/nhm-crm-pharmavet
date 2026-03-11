<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->timestamp('ghn_created_at')->nullable()->after('ghn_status_label');
            $table->timestamp('ghn_updated_at')->nullable()->after('ghn_created_at');
            $table->json('ghn_items')->nullable()->after('ghn_updated_at');
            $table->integer('ghn_payment_type_id')->nullable()->after('ghn_items');
            $table->integer('ghn_weight')->nullable()->after('ghn_payment_type_id');
            $table->text('ghn_content')->nullable()->after('ghn_weight');
            $table->string('ghn_required_note')->nullable()->after('ghn_content');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropColumn([
                'ghn_created_at',
                'ghn_updated_at',
                'ghn_items',
                'ghn_payment_type_id',
                'ghn_weight',
                'ghn_content',
                'ghn_required_note'
            ]);
        });
    }
};
