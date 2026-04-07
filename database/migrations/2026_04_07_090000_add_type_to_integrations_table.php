<?php

use App\Common\Constants\Marketing\IntegrationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('integrations')) {
            return;
        }

        if (!Schema::hasColumn('integrations', 'type')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->unsignedTinyInteger('type')->nullable()->after('name');
                $table->index('type', 'idx_integrations_type');
            });
        }

        DB::table('integrations')
            ->select(['id', 'type', 'config'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if ($row->type !== null) {
                        continue;
                    }

                    $config = [];
                    if (is_string($row->config) && $row->config !== '') {
                        $decoded = json_decode($row->config, true);
                        $config = is_array($decoded) ? $decoded : [];
                    } elseif (is_array($row->config)) {
                        $config = $row->config;
                    }

                    $type = IntegrationType::MANUAL_DATA->value;
                    if (!empty($config['site_id'])) {
                        $type = IntegrationType::WEBSITE->value;
                    } elseif (!empty($config['pixel_id']) || !empty($config['webhook_verify_token'])) {
                        $type = IntegrationType::FACEBOOK_ADS->value;
                    }

                    DB::table('integrations')
                        ->where('id', $row->id)
                        ->update(['type' => $type]);
                }
            });
    }

    public function down(): void
    {
        // Compatibility-first migration: no-op rollback.
    }
};

