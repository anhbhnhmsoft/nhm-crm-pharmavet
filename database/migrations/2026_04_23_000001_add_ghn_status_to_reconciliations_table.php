<?php

use App\Common\Constants\Order\GhnOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->string('ghn_status', 50)->nullable()->after('ghn_to_address');
        });

        DB::table('reconciliations')
            ->select(['id', 'order_id', 'ghn_status_label'])
            ->orderBy('id')
            ->chunkById(200, function ($reconciliations): void {
                $orderStatuses = DB::table('orders')
                    ->whereIn('id', $reconciliations->pluck('order_id')->filter()->all())
                    ->pluck('ghn_status', 'id');

                foreach ($reconciliations as $reconciliation) {
                    $ghnStatus = GhnOrderStatus::normalize($reconciliation->ghn_status_label);

                    if ($ghnStatus === null && filled($reconciliation->order_id)) {
                        $ghnStatus = GhnOrderStatus::normalize($orderStatuses[$reconciliation->order_id] ?? null);
                    }

                    DB::table('reconciliations')
                        ->where('id', $reconciliation->id)
                        ->update(['ghn_status' => $ghnStatus]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropColumn('ghn_status');
        });
    }
};
