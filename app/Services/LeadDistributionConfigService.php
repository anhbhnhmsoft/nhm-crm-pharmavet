<?php

namespace App\Services;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\Team\TeamType;
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
                    $this->createDefaultRules($config);
                }

                $config->load(['rules', 'staffSale.teams', 'staffCSKH.teams']);

                return ServiceReturn::success($config);
            });
        } catch (Throwable $thr) {
            Log::error('getLeadDistributionConfig error: ' . $thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }

    public function saveLeadDistributionConfig(?LeadDistributionConfig $config, array $data, ?int $organizationId = null)
    {
        try {
            return DB::transaction(function () use ($config, $data, $organizationId) {
                if (! $config) {
                    if (! $organizationId) {
                        return ServiceReturn::error(__('common.error.data_not_found'));
                    }

                    $config = $this->leadDistributionConfigRepository
                        ->query()
                        ->firstOrCreate(
                            ['organization_id' => $organizationId],
                            [
                                'name' => __('filament.lead.label'),
                                'product_id' => null,
                                'created_by' => Auth::id(),
                                'updated_by' => Auth::id(),
                            ]
                        );
                }

                $config->update([
                    'name' => $data['name'],
                    'product_id' => empty($data['product_id']) ? null : $data['product_id'],
                    'updated_by' => Auth::id(),
                ]);

                if (!empty($data['rules'])) {
                    $ruleIds = collect($data['rules'])->pluck('id')->filter()->toArray();

                    $config->rules()->whereNotIn('id', $ruleIds)->delete();

                    foreach ($data['rules'] as $ruleData) {
                        $attributes = [
                            'customer_type' => $ruleData['customer_type'] ?? null,
                            'staff_type' => $ruleData['staff_type'] ?? null,
                            'distribution_method' => $ruleData['distribution_method'] ?? null,
                        ];

                        if (! empty($ruleData['id'])) {
                            $config->rules()->updateOrCreate(
                                [
                                    'id' => $ruleData['id'],
                                    'config_id' => $config->id,
                                ],
                                $attributes,
                            );

                            continue;
                        }

                        $config->rules()->updateOrCreate(
                            [
                                'config_id' => $config->id,
                                'customer_type' => $ruleData['customer_type'] ?? null,
                                'staff_type' => $ruleData['staff_type'] ?? null,
                            ],
                            $attributes,
                        );
                    }
                } else {
                    $this->createDefaultRules($config);
                }

                $this->syncStaffGroup($config, $data['staffSale'] ?? [], 'SALE');
                $this->syncStaffGroup($config, $data['staffCSKH'] ?? [], 'CSKH');

                $config->load(['rules', 'staffSale.teams', 'staffCSKH.teams']);

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
        $validStaff = collect($staffData)
            ->filter(fn($item) => !empty($item['staff_id']))
            ->mapWithKeys(fn($item) => [
                $item['staff_id'] => ['weight' => $item['weight'] ?? 1]
            ]);

        $relation = match ($type) {
            'SALE' => $config->staffSale(),
            'CSKH' => $config->staffCSKH(),
            default => $config->staff(),
        };

        $currentStaffIds = $relation->pluck('users.id')->toArray();

        if ($validStaff->isEmpty()) {
            if ($currentStaffIds !== []) {
                $config->staff()->detach($currentStaffIds);
            }

            return;
        }

        $newStaffIds = $validStaff->keys()->toArray();

        $toRemove = array_diff($currentStaffIds, $newStaffIds);
        if (!empty($toRemove)) {
            $config->staff()->detach($toRemove);
        }

        $config->staff()->syncWithoutDetaching($validStaff->toArray());
    }

    private function createDefaultRules(LeadDistributionConfig $config): void
    {
        $defaults = collect(CustomerType::cases())
            ->map(fn (CustomerType $customerType) => [
                'customer_type' => $customerType->value,
                'staff_type' => TeamType::SALE->value,
                'distribution_method' => DistributionMethod::MOST_RECENT_RECIPIENT->value,
            ]);

        foreach ($defaults as $attributes) {
            $config->rules()->firstOrCreate($attributes, $attributes);
        }
    }
}
