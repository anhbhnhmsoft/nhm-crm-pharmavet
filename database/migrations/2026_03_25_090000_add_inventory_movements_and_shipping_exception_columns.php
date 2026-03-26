<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('ref_type', 50)->nullable();
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->string('movement_type', 20);
                $table->integer('quantity_before')->default(0);
                $table->integer('quantity_change')->default(0);
                $table->integer('quantity_after')->default(0);
                $table->integer('pending_before')->default(0);
                $table->integer('pending_change')->default(0);
                $table->integer('pending_after')->default(0);
                $table->string('reason_code', 50)->nullable();
                $table->string('reason_note', 255)->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();

                $table->index(['organization_id', 'warehouse_id', 'product_id'], 'idx_inv_move_org_wh_product');
                $table->index(['ref_type', 'ref_id'], 'idx_inv_move_ref');
                $table->index(['movement_type', 'occurred_at'], 'idx_inv_move_type_time');
            });
        }

        if (Schema::hasTable('inventory_ticket_logs')) {
            Schema::table('inventory_ticket_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_ticket_logs', 'action')) {
                    $table->string('action', 50)->nullable()->after('reason');
                }
                if (!Schema::hasColumn('inventory_ticket_logs', 'old_status')) {
                    $table->unsignedTinyInteger('old_status')->nullable()->after('action');
                }
                if (!Schema::hasColumn('inventory_ticket_logs', 'new_status')) {
                    $table->unsignedTinyInteger('new_status')->nullable()->after('old_status');
                }
                if (!Schema::hasColumn('inventory_ticket_logs', 'metadata_json')) {
                    $table->json('metadata_json')->nullable()->after('new_status');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'shipping_exception_reason_code')) {
                    $table->string('shipping_exception_reason_code', 50)->nullable()->after('ghn_status');
                }
                if (!Schema::hasColumn('orders', 'shipping_exception_note')) {
                    $table->text('shipping_exception_note')->nullable()->after('shipping_exception_reason_code');
                }
                if (!Schema::hasColumn('orders', 'redelivery_attempt')) {
                    $table->unsignedTinyInteger('redelivery_attempt')->default(0)->after('shipping_exception_note');
                }
                if (!Schema::hasColumn('orders', 'redelivery_schedule_at')) {
                    $table->timestamp('redelivery_schedule_at')->nullable()->after('redelivery_attempt');
                }
            });
        }

        if (Schema::hasTable('inventory_ticket_details')) {
            Schema::table('inventory_ticket_details', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_ticket_details', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('inventory_ticket_details', 'batch_no')) {
                    $table->string('batch_no', 100)->nullable()->after('unit_price');
                }
                if (!Schema::hasColumn('inventory_ticket_details', 'expired_at')) {
                    $table->date('expired_at')->nullable()->after('batch_no');
                }
                if (!Schema::hasColumn('inventory_ticket_details', 'bin_location_id')) {
                    $table->unsignedBigInteger('bin_location_id')->nullable()->after('expired_at');
                }
            });
        }

        if (!Schema::hasTable('warehouse_bins')) {
            Schema::create('warehouse_bins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name', 120);
                $table->boolean('allow_mix_sku')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['warehouse_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warehouse_bins')) {
            Schema::dropIfExists('warehouse_bins');
        }

        if (Schema::hasTable('inventory_ticket_details')) {
            Schema::table('inventory_ticket_details', function (Blueprint $table) {
                $drops = [];
                foreach (['bin_location_id', 'expired_at', 'batch_no', 'unit_price'] as $column) {
                    if (Schema::hasColumn('inventory_ticket_details', $column)) {
                        $drops[] = $column;
                    }
                }

                if (!empty($drops)) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $drops = [];
                foreach (['redelivery_schedule_at', 'redelivery_attempt', 'shipping_exception_note', 'shipping_exception_reason_code'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $drops[] = $column;
                    }
                }

                if (!empty($drops)) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasTable('inventory_ticket_logs')) {
            Schema::table('inventory_ticket_logs', function (Blueprint $table) {
                $drops = [];
                foreach (['metadata_json', 'new_status', 'old_status', 'action'] as $column) {
                    if (Schema::hasColumn('inventory_ticket_logs', $column)) {
                        $drops[] = $column;
                    }
                }

                if (!empty($drops)) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasTable('inventory_movements')) {
            Schema::dropIfExists('inventory_movements');
        }
    }
};
