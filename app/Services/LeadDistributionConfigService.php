<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\LeadDistributionConfigRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\LeadDistributionConfig;
use Illuminate\Support\Facades\DB;
use Throwable;

class LeadDistributionConfigService
{
    protected LeadDistributionConfigRepository $leadDistributionConfigRepository;

    public function __construct(LeadDistributionConfigRepository $leadDistributionConfigRepository)
    {
        $this->leadDistributionConfigRepository = $leadDistributionConfigRepository;
    }

    public function getLeadDistributionConfig(int $organizationId)
    {
        try {
            return DB::transaction(function () use ($organizationId) {
                $config = $this->leadDistributionConfigRepository
                    ->query()
                    ->with('rules', 'staffSale', 'staffCSKH')
                    ->firstOrCreate(
                        ['organization_id' => $organizationId],
                        [
                            'name' => __('filament.lead.label'),
                            'product_id' => null,
                            'created_by' => Auth::user()->id,
                            'updated_by' => Auth::user()->id,
                        ]
                    );

                if ($config->rules->isEmpty()) {
                    $config->load('rules');
                }

                return ServiceReturn::success($config);
            });
        } catch (Throwable $thr) {
            Log::error('getLeadDistributionConfig error: ' . $thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }

    public function saveLeadDistributionConfig(?LeadDistributionConfig $config, array $data)
    {
        try {
            return DB::transaction(function () use ($config, $data) {
                $config->update([
                    'name' => $data['name'],
                    'product_id' => empty($data['product_id']) ? null : $data['product_id'],
                    'updated_by' => Auth::id(),
                ]);

                if (!empty($data['rules'])) {
                    $ruleIds = collect($data['rules'])->pluck('id')->filter()->toArray();

                    $config->rules()->whereNotIn('id', $ruleIds)->delete();

                    foreach ($data['rules'] as $ruleData) {
                        $config->rules()->updateOrCreate(
                            [
                                'config_id' => $config->id,
                                'customer_type' => $ruleData['customer_type'] ?? null,
                                'staff_type' => $ruleData['staff_type'] ?? null,
                                'distribution_method' => $ruleData['distribution_method'] ?? null,
                            ],
                            [
                                'customer_type' => $ruleData['customer_type'] ?? null,
                                'staff_type' => $ruleData['staff_type'] ?? null,
                                'distribution_method' => $ruleData['distribution_method'] ?? null,
                            ]
                        );
                    }
                }

                $this->syncStaffGroup($config, $data['staffSale'] ?? [], 'SALE');
                $this->syncStaffGroup($config, $data['staffCSKH'] ?? [], 'CSKH');

                $config->load(['rules', 'staffSale', 'staffCSKH']);

                return ServiceReturn::success($config);
            });
        } catch (Throwable $e) {
            Log::error('saveLeadDistributionConfig error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            return ServiceReturn::error($e->getMessage());
        }
    }

    private function syncStaffGroup(LeadDistributionConfig $config, array $staffData, string $type): void
    {
        if (empty($staffData)) {
            return;
        }

        $validStaff = collect($staffData)
            ->filter(fn($item) => !empty($item['staff_id']))
            ->mapWithKeys(fn($item) => [
                $item['staff_id'] => ['weight' => $item['weight'] ?? 1]
            ]);

        if ($validStaff->isEmpty()) {
            return;
        }

        $relation = match ($type) {
            'SALE' => $config->staffSale(),
            'CSKH' => $config->staffCSKH(),
            default => $config->staff(),
        };

        $currentStaffIds = $relation->pluck('users.id')->toArray();
        $newStaffIds = $validStaff->keys()->toArray();

        $toRemove = array_diff($currentStaffIds, $newStaffIds);
        if (!empty($toRemove)) {
            $config->staff()->detach($toRemove);
        }

        $config->staff()->syncWithoutDetaching($validStaff->toArray());
    }
}
