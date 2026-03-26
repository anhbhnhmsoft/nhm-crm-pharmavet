<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funds')) {
            Schema::table('funds', function (Blueprint $table) {
                if (!Schema::hasColumn('funds', 'fund_type')) {
                    $table->string('fund_type', 20)->default('cash')->after('currency');
                }
            });
        }

        if (Schema::hasTable('fund_transactions')) {
            Schema::table('fund_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('fund_transactions', 'transaction_date')) {
                    $table->date('transaction_date')->nullable()->after('fund_id');
                }
                if (!Schema::hasColumn('fund_transactions', 'counterparty_name')) {
                    $table->string('counterparty_name')->nullable()->after('amount');
                }
                if (!Schema::hasColumn('fund_transactions', 'currency')) {
                    $table->string('currency', 3)->default('VND')->after('counterparty_name');
                }
                if (!Schema::hasColumn('fund_transactions', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 14, 6)->nullable()->after('currency');
                }
                if (!Schema::hasColumn('fund_transactions', 'amount_base')) {
                    $table->decimal('amount_base', 20, 2)->nullable()->after('exchange_rate');
                }
                if (!Schema::hasColumn('fund_transactions', 'purpose')) {
                    $table->string('purpose')->nullable()->after('description');
                }
                if (!Schema::hasColumn('fund_transactions', 'note')) {
                    $table->text('note')->nullable()->after('purpose');
                }
                if (!Schema::hasColumn('fund_transactions', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('note');
                }
            });

            Schema::table('fund_transactions', function (Blueprint $table) {
                $table->index(['fund_id', 'transaction_date'], 'idx_fund_tx_fund_date');
            });
        }

        if (!Schema::hasTable('fund_lock_rules')) {
            Schema::create('fund_lock_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
                $table->string('action', 20); // add|edit|delete
                $table->string('scope_type', 20)->default('global'); // global|user|team
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->boolean('is_locked')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['fund_id', 'action', 'scope_type'], 'idx_fund_lock_scope');
                $table->index(['user_id', 'action'], 'idx_fund_lock_user');
                $table->index(['team_id', 'action'], 'idx_fund_lock_team');
            });
        }

        if (!Schema::hasTable('fund_lock_audits')) {
            Schema::create('fund_lock_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
                $table->string('action', 20);
                $table->boolean('is_locked');
                $table->string('scope_type', 20)->default('global');
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->unsignedBigInteger('target_team_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('changed_at')->nullable();
                $table->timestamps();

                $table->index(['fund_id', 'changed_at'], 'idx_fund_lock_audit_time');
            });
        }

        if (!Schema::hasTable('fund_transaction_attachments')) {
            Schema::create('fund_transaction_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_transaction_id')->constrained('fund_transactions')->cascadeOnDelete();
                $table->unsignedInteger('version')->default(1);
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();

                $table->unique(['fund_transaction_id', 'version'], 'uq_fund_tx_attachment_version');
                $table->index(['fund_transaction_id', 'uploaded_at'], 'idx_fund_tx_attachment_time');
            });
        }
    }

    public function down(): void
    {
        // Compatibility-first migration: no-op rollback for core accounting data safety.
    }
};
