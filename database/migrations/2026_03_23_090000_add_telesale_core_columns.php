<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'duplicate_hash')) {
                $table->string('duplicate_hash', 191)->nullable()->after('source_id')->index();
            }
        });

        Schema::table('customer_interactions', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_interactions', 'channel')) {
                $table->string('channel', 20)->nullable()->after('type')->index();
            }
            if (!Schema::hasColumn('customer_interactions', 'attempt_no')) {
                $table->unsignedSmallInteger('attempt_no')->default(1)->after('channel');
            }
            if (!Schema::hasColumn('customer_interactions', 'care_no')) {
                $table->unsignedSmallInteger('care_no')->default(1)->after('attempt_no');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cod_support_amount')) {
                $table->decimal('cod_support_amount', 15, 2)->default(0)->after('cod_fee');
            }
            if (!Schema::hasColumn('orders', 'collect_amount')) {
                $table->decimal('collect_amount', 15, 2)->default(0)->after('cod_support_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'collect_amount')) {
                $table->dropColumn('collect_amount');
            }
            if (Schema::hasColumn('orders', 'cod_support_amount')) {
                $table->dropColumn('cod_support_amount');
            }
        });

        Schema::table('customer_interactions', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('customer_interactions', 'care_no')) {
                $drops[] = 'care_no';
            }
            if (Schema::hasColumn('customer_interactions', 'attempt_no')) {
                $drops[] = 'attempt_no';
            }
            if (Schema::hasColumn('customer_interactions', 'channel')) {
                $drops[] = 'channel';
            }
            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'duplicate_hash')) {
                $table->dropColumn('duplicate_hash');
            }
        });
    }
};
