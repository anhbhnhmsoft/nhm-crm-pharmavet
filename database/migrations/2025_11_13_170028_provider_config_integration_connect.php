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
//        Schema::create('integrations', function (Blueprint $table) {
//            $table->id();
//
//            // Mỗi tổ chức có nhiều integration
//            $table->foreignId('organization_id')
//                ->constrained('organizations')
//                ->cascadeOnDelete();
//
//            $table->string('name'); // Tên cấu hình hiển thị
//            $table->unsignedTinyInteger('status')->default(0); // 0=pending,1=connected,2=expired,3=error
//            $table->text('status_message')->nullable();
//            $table->timestamp('last_sync_at')->nullable();
//            $table->unsignedTinyInteger('type');
//
//            // Cấu hình chung (field mapping, webhook settings, meta config…)
//            $table->json('config')->nullable();
//            $table->json('field_mapping')->nullable();
//
//            // Audit
//            $table->unsignedBigInteger('created_by')->nullable();
//            $table->unsignedBigInteger('updated_by')->nullable();
//
//            $table->timestamps();
//            $table->softDeletes();
//
//            $table->index(['organization_id']);
//
//            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
//            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
//        });
//
//        Schema::create('integration_entities', function (Blueprint $table) {
//            $table->id();
//
//            $table->foreignId('integration_id')
//                ->constrained('integrations')
//                ->cascadeOnDelete();
//
//            $table->unsignedTinyInteger('type');
//            // Ví dụ: page, business_account, ad_account, pixel, domain, webhook, catalog…
//
//            $table->string('external_id', 100);
//            $table->string('name')->nullable();
//
//            $table->json('metadata')->nullable(); // email, timezone, permissions, picture…
//
//            $table->unsignedTinyInteger('status')->default(1); // active, inactive…
//
//            $table->timestamp('connected_at')->nullable();
//
//            $table->timestamps();
//            $table->softDeletes();
//
//            $table->index(['integration_id', 'type']);
//            $table->index('external_id');
//        });
//
//        Schema::create('integration_tokens', function (Blueprint $table) {
//            $table->id();
//
//            $table->foreignId('integration_id')
//                ->constrained('integrations')
//                ->cascadeOnDelete();
//
//            // Token có thể liên quan đến 1 entity nào đó (Page/BMA/AdAccount)
//            $table->foreignId('entity_id')
//                ->nullable()
//                ->constrained('integration_entities')
//                ->nullOnDelete();
//
//            $table->unsignedTinyInteger('type');
//            // user_token, long_lived_user_token, business_token, page_token, webhook_secret…
//
//            $table->text('token'); // encrypted
//            $table->json('scopes')->nullable();
//            $table->timestamp('expires_at')->nullable();
//            $table->unsignedTinyInteger('status')->default(1); // active, expired
//
//            $table->timestamps();
//            $table->softDeletes();
//
//            $table->index(['integration_id', 'type']);
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::dropIfExists('integration_tokens');
//        Schema::dropIfExists('integration_entities');
//        Schema::dropIfExists('integrations');
    }
};
