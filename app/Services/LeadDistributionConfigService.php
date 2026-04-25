<?php

namespace App\Services;

use App\Common\Constants\Team\TeamType;
use App\Core\ServiceReturn;
use App\Models\LeadDistributionConfig;
use App\Models\Team;
use App\Models\User;
use App\Repositories\LeadDistributionConfigRepository;
use App\Support\LeadDistributionConfigRuleMatrix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $thr) {
            Log::error('getLeadDistributionConfig error: ' . $thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }

    public function saveLeadDistributionConfig(?LeadDistributionConfig $config, array $data, ?int $organizationId = null)
    {
        try {
            return DB::transaction(function () use ($config, $data, $organizationId) {
                $resolvedOrganizationId = (int) ($config?->organization_id ?? $organizationId ?? 0);

                if ($resolvedOrganizationId <= 0) {
                    return ServiceReturn::error(__('common.error.data_not_found'));
                }

                $this->validateConfigurationData($data, $resolvedOrganizationId);

                if (! $config) {
                    $config = $this->leadDistributionConfigRepository
                        ->query()
                        ->firstOrCreate(
                            ['organization_id' => $resolvedOrganizationId],
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

                $this->syncRules(
                    $config,
                    LeadDistributionConfigRuleMatrix::normalizeForForm($data['rules'] ?? []),
                );

                $this->syncStaffGroup($config, $data['staffSale'] ?? [], TeamType::SALE);
                $this->syncStaffGroup($config, $data['staffCSKH'] ?? [], TeamType::CSKH);

                $config->load(['rules', 'staffSale.teams', 'staffCSKH.teams']);

                return ServiceReturn::success($config);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $e) {
            Log::error('saveLeadDistributionConfig error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            return ServiceReturn::error($e->getMessage());
        }
    }

    private function validateConfigurationData(array $data, int $organizationId): void
    {
        $errors = $this->prefixValidationKeys(
            LeadDistributionConfigRuleMatrix::validationErrors($data['rules'] ?? []),
        );

        $errors = array_merge(
            $errors,
            $this->validateStaffGroup($data['staffSale'] ?? [], TeamType::SALE, $organizationId, 'staffSale'),
            $this->validateStaffGroup($data['staffCSKH'] ?? [], TeamType::CSKH, $organizationId, 'staffCSKH'),
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function prefixValidationKeys(array $errors): array
    {
        return collect($errors)
            ->mapWithKeys(
                fn (array|string $messages, string $field): array => ["data.{$field}" => (array) $messages]
            )
            ->all();
    }

    private function validateStaffGroup(array $staffData, TeamType $teamType, int $organizationId, string $field): array
    {
        $errors = [];
        $seenStaff = [];

        $teamIds = collect($staffData)
            ->pluck('team_id')
            ->filter(fn ($teamId) => filled($teamId))
            ->map(fn ($teamId): int => (int) $teamId)
            ->unique()
            ->values()
            ->all();

        $validTeamIds = Team::query()
            ->where('organization_id', $organizationId)
            ->where('type', $teamType->value)
            ->whereIn('id', $teamIds === [] ? [0] : $teamIds)
            ->pluck('id')
            ->map(fn ($teamId): int => (int) $teamId)
            ->all();

        $staffIds = collect($staffData)
            ->pluck('staff_id')
            ->filter(fn ($staffId) => filled($staffId))
            ->map(fn ($staffId): int => (int) $staffId)
            ->unique()
            ->values()
            ->all();

        $staffById = User::query()
            ->where('organization_id', $organizationId)
            ->where('disable', false)
            ->whereIn('id', $staffIds === [] ? [0] : $staffIds)
            ->with(['teams' => fn ($query) => $query->select('teams.id', 'teams.type')])
            ->get()
            ->keyBy('id');

        foreach (array_values($staffData) as $index => $item) {
            $prefix = "data.{$field}.{$index}";
            $teamId = (int) ($item['team_id'] ?? 0);
            $staffId = (int) ($item['staff_id'] ?? 0);
            $weight = $item['weight'] ?? null;

            if ($teamId <= 0) {
                $errors["{$prefix}.team_id"][] = __('common.error.required');
            } elseif (! in_array($teamId, $validTeamIds, true)) {
                $errors["{$prefix}.team_id"][] = __('filament.lead.staff.validation.invalid_team');
            }

            if ($staffId <= 0) {
                $errors["{$prefix}.staff_id"][] = __('common.error.required');
            } else {
                if (isset($seenStaff[$staffId])) {
                    $firstIndex = $seenStaff[$staffId];
                    $message = __('filament.lead.staff.validation.duplicate');

                    $errors["data.{$field}.{$firstIndex}.staff_id"][] = $message;
                    $errors["{$prefix}.staff_id"][] = $message;
                } else {
                    $seenStaff[$staffId] = $index;
                }

                $staff = $staffById->get($staffId);

                if (
                    ! $staff
                    || $teamId <= 0
                    || ! in_array($teamId, $validTeamIds, true)
                    || ! $staff->teams->contains(fn ($team) => (int) $team->id === $teamId && (int) $team->type === $teamType->value)
                ) {
                    $errors["{$prefix}.staff_id"][] = __('filament.lead.staff.validation.invalid_staff');
                }
            }

            if ($weight === null || $weight === '') {
                $errors["{$prefix}.weight"][] = __('common.error.required');
                continue;
            }

            if (filter_var($weight, FILTER_VALIDATE_INT) === false) {
                $errors["{$prefix}.weight"][] = __('common.error.integer');
                continue;
            }

            $weightValue = (int) $weight;

            if ($weightValue < 1) {
                $errors["{$prefix}.weight"][] = __('common.error.min_value', ['min' => 1]);
                continue;
            }

            if ($weightValue > 100) {
                $errors["{$prefix}.weight"][] = __('common.error.max_value', ['max' => 100]);
            }
        }

        return $errors;
    }

    private function syncRules(LeadDistributionConfig $config, array $rules): void
    {
        $expectedKeys = LeadDistributionConfigRuleMatrix::expectedKeys();
        $existingRules = $config->rules()
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($rule) => LeadDistributionConfigRuleMatrix::buildKey($rule->customer_type, $rule->staff_type));

        $keptRules = [];

        foreach ($existingRules as $key => $group) {
            if (! in_array($key, $expectedKeys, true)) {
                $group->each->delete();

                continue;
            }

            $primaryRule = $group->first();

            if (! $primaryRule) {
                continue;
            }

            $group->slice(1)->each->delete();
            $keptRules[$key] = $primaryRule;
        }

        foreach ($rules as $ruleData) {
            $key = LeadDistributionConfigRuleMatrix::buildKey(
                $ruleData['customer_type'] ?? null,
                $ruleData['staff_type'] ?? null,
            );

            $attributes = [
                'customer_type' => (int) $ruleData['customer_type'],
                'staff_type' => (int) $ruleData['staff_type'],
            ];

            $payload = [
                ...$attributes,
                'distribution_method' => (int) $ruleData['distribution_method'],
            ];

            if (isset($keptRules[$key])) {
                $keptRules[$key]->update($payload);
                continue;
            }

            $config->rules()->create($payload);
        }
    }

    private function syncStaffGroup(LeadDistributionConfig $config, array $staffData, TeamType $type): void
    {
        $validStaff = collect($staffData)
            ->filter(fn ($item) => ! empty($item['staff_id']))
            ->mapWithKeys(fn ($item) => [
                (int) $item['staff_id'] => ['weight' => (int) ($item['weight'] ?? 1)],
            ]);

        $relation = match ($type) {
            TeamType::SALE => $config->staffSale(),
            TeamType::CSKH => $config->staffCSKH(),
            default => $config->staff(),
        };

        $currentStaffIds = $relation->pluck('users.id')->map(fn ($id): int => (int) $id)->toArray();

        if ($validStaff->isEmpty()) {
            if ($currentStaffIds !== []) {
                $config->staff()->detach($currentStaffIds);
            }

            return;
        }

        $newStaffIds = $validStaff->keys()->toArray();

        $toRemove = array_diff($currentStaffIds, $newStaffIds);
        if (! empty($toRemove)) {
            $config->staff()->detach($toRemove);
        }

        $config->staff()->syncWithoutDetaching($validStaff->toArray());
    }

    private function createDefaultRules(LeadDistributionConfig $config): void
    {
        $this->syncRules($config, LeadDistributionConfigRuleMatrix::defaultRules());
    }
}
