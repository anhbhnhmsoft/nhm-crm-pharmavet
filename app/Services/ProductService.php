<?php

namespace App\Services;

use App\Common\Constants\Team\TeamType;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class ProductService
{
    /**
     * Đồng bộ user assignments qua pivot table
     */
    protected static function syncUserAssignments($productId, array $data): void
    {
        DB::transaction(function () use ($productId, $data) {
            // Xóa tất cả assignments cũ
            $productId->userAssignments()->delete();

            $assignments = [];

            // Sales users
            if (!empty($data['sales_user_ids'])) {
                foreach ($data['sales_user_ids'] as $userId) {
                    $assignments[] = [
                        'product_id' => $productId->id,
                        'user_id' => $userId,
                        'type' => TeamType::SALE->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Marketing users
            if (!empty($data['marketing_user_ids'])) {
                foreach ($data['marketing_user_ids'] as $userId) {
                    $assignments[] = [
                        'product_id' => $productId->id,
                        'user_id' => $userId,
                        'type' => TeamType::MARKETING->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // CSKH users
            if (!empty($data['cskh_user_ids'])) {
                foreach ($data['cskh_user_ids'] as $userId) {
                    $assignments[] = [
                        'product_id' => $productId->id,
                        'user_id' => $userId,
                        'type' => TeamType::CSKH->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Insert tất cả assignments
            if (!empty($assignments)) {
                DB::table('product_user_assignments')->insert($assignments);
            }
        });
    }

    /**
     * Load relationships khi edit
     */
    public static function mutateFormDataBeforeFill(array $data): array
    {
        $productId = Product::with(['userAssignments'])->find($data['id']);

        if ($productId) {
            // Load sales users
            $data['sales_user_ids'] = $productId->userAssignments()
                ->where('type', TeamType::SALE->value)
                ->pluck('user_id')
                ->toArray();

            // Load marketing users
            $data['marketing_user_ids'] = $productId->userAssignments()
                ->where('type', TeamType::MARKETING->value)
                ->pluck('user_id')
                ->toArray();

            // Load CSKH users
            $data['cskh_user_ids'] = $productId->userAssignments()
                ->where('type', TeamType::CSKH->value)
                ->pluck('user_id')
                ->toArray();

            // Load team IDs từ users đầu tiên (giả định cùng team)
            if (!empty($data['sales_user_ids'])) {
                $data['sales_team_id'] = \App\Models\User::find($data['sales_user_ids'][0])?->team_id;
            }

            if (!empty($data['marketing_user_ids'])) {
                $data['marketing_team_id'] = \App\Models\User::find($data['marketing_user_ids'][0])?->team_id;
            }

            if (!empty($data['cskh_user_ids'])) {
                $data['cskh_team_id'] = \App\Models\User::find($data['cskh_user_ids'][0])?->team_id;
            }
        }

        return $data;
    }
}
