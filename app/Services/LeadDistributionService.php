<?php

namespace App\Services;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\DistributionMethod;
use App\Models\Customer;
use App\Models\LeadDistributionConfig;
use App\Models\LeadDistributionRule;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Repositories\LeadDistributionConfigRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadDistributionService
{
    protected    CustomerRepository $customerRepository;
    protected    LeadDistributionConfigRepository $leadDistributionConfigRepository;
    protected    UserRepository $userRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        LeadDistributionConfigRepository $leadDistributionConfigRepository,
        UserRepository $userRepository,
    ) {
        $this->customerRepository = $customerRepository;
        $this->leadDistributionConfigRepository = $leadDistributionConfigRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Phân bổ lead cho nhân viên
     */
    public function assignLead(Customer $customer, int $productId, int $organizationId): ?User
    {
        try {
            // Lấy cấu hình phân bổ
            $config = $this->getActiveConfig($organizationId, $productId);

            if (!$config) {
                Log::warning('No active distribution config found', [
                    'organization_id' => $organizationId,
                    'product_id' => $productId,
                ]);
                return null;
            }

            // Lấy rule phù hợp
            $rule = $this->getMatchingRule($config, $customer->customer_type);

            if (!$rule) {
                Log::warning('No matching rule found', [
                    'config_id' => $config->id,
                    'customer_type' => $customer->customer_type,
                ]);
                return null;
            }

            // Lấy danh sách staff có thể nhận lead
            $eligibleStaff = $this->getEligibleStaff($config, $rule->staff_type);

            if ($eligibleStaff->isEmpty()) {
                Log::warning('No eligible staff found', [
                    'config_id' => $config->id,
                    'staff_type' => $rule->staff_type,
                ]);
                return null;
            }

            // Chọn staff theo phương thức phân bổ
            $selectedStaff = $this->selectStaffByMethod(
                $eligibleStaff,
                $rule->distribution_method,
                $config->id
            );

            if (!$selectedStaff) {
                return null;
            }

            Log::info('Lead assigned successfully', [
                'customer_id' => $customer->id,
                'staff_id' => $selectedStaff->id,
                'distribution_method' => $rule->distribution_method,
            ]);

            return $selectedStaff;
        } catch (\Exception $e) {
            Log::error('Lead assignment failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Lấy cấu hình phân bổ đang hoạt động
     */
    protected function getActiveConfig(int $organizationId, int $productId): ?LeadDistributionConfig
    {
        return $this->leadDistributionConfigRepository->query()->with(['rules', 'staff'])
            ->where('organization_id', $organizationId)
            ->orWhere('product_id', $productId)
            ->first();
    }

    /**
     * Lấy rule phù hợp
     */
    protected function getMatchingRule(LeadDistributionConfig $config, int $customerType): ?LeadDistributionRule
    {
        return $config->rules()->query()->where('customer_type', $customerType)->first();
    }

    /**
     * Lấy danh sách staff có thể nhận lead
     */
    protected function getEligibleStaff(LeadDistributionConfig $config, int $staffType): Collection
    {
        // Lấy staff từ config
        $staffIds = $config->staff->pluck('id')->toArray();

        if (empty($staffIds)) {
            return collect();
        }

        // Filter staff theo điều kiện
        return User::whereIn('id', $staffIds)
            ->whereHas('team', function ($query) use ($staffType, $config) {
                $query->where('organization_id', $config->organization_id)
                    ->where('type', $staffType);
            })
            ->where('disable', false) // Không bị khóa
            ->whereNull('deleted_at') // Không bị xóa
            // TODO: Add thêm điều kiện trong ca làm việc nếu cần
            ->get();
    }

    /**
     * Chọn staff theo phương thức phân bổ
     */
    protected function selectStaffByMethod(Collection $staff, int $method, int $configId): ?User
    {
        $methodEnum = DistributionMethod::tryFrom($method);

        return match ($methodEnum) {
            DistributionMethod::MOST_RECENT_RECIPIENT => $this->selectByRoundRobin($staff, $configId),
            DistributionMethod::BY_DEFINITION => $this->selectByDefinition($staff, $configId),
            default => $this->selectByRoundRobin($staff, $configId),
        };
    }

    /**
     * Round Robin - Chia đều theo vòng tròn (Dựa trên người nhận gần nhất)
     */
    protected function selectByRoundRobin(Collection $staff, int $configId): ?User
    {
        $cacheKey = "lead_distribution:round_robin:{$configId}";

        // Lấy index hiện tại
        $currentIndex = Cache::get($cacheKey, 0);

        // Chọn staff theo index
        $selectedStaff = $staff->values()->get($currentIndex);

        // Nếu staff tại index hiện tại không tồn tại (do danh sách staff thay đổi), reset về 0
        if (!$selectedStaff) {
            $currentIndex = 0;
            $selectedStaff = $staff->values()->get($currentIndex);
        }

        // Tăng index cho lần sau
        $nextIndex = ($currentIndex + 1) % $staff->count();
        Cache::put($cacheKey, $nextIndex, now()->addDays(7));

        return $selectedStaff;
    }

    /**
     * Weighted - Chia theo định mức (Trọng số)
     */
    protected function selectByDefinition(Collection $staff, int $configId): ?User
    {
        // Lấy thông tin trọng số từ pivot
        $config = $this->leadDistributionConfigRepository->find($configId);

        if (!$config) {
            return $staff->random();
        }

        $staffWithWeights = $config->staff()
            ->whereIn('users.id', $staff->pluck('id'))
            ->get()
            ->map(function ($user) {
                return [
                    'user' => $user,
                    'weight' => $user->pivot->weight ?? 1,
                ];
            });

        if ($staffWithWeights->isEmpty()) {
            return $staff->random();
        }

        // Tính tổng trọng số
        $totalWeight = $staffWithWeights->sum('weight');

        // Random theo trọng số
        $random = rand(1, $totalWeight);
        $accumulated = 0;

        foreach ($staffWithWeights as $item) {
            $accumulated += $item['weight'];
            if ($random <= $accumulated) {
                return $item['user'];
            }
        }

        return $staffWithWeights->first()['user'];
    }

    /**
     * Lấy thống kê phân bổ
     */
    public function getDistributionStats(int $configId, $startDate = null, $endDate = null): array
    {
        $config = $this->leadDistributionConfigRepository->find($configId);

        if (!$config) {
            return [];
        }

        $query = $this->customerRepository->query()->where('organization_id', $config->organization_id)
            ->whereNotNull('assigned_staff_id');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $stats = $query->select('assigned_staff_id', DB::raw('count(*) as total'))
            ->groupBy('assigned_staff_id')
            ->with('assignedStaff:id,name')
            ->get();

        return $stats->map(function ($stat) {
            return [
                'staff_id' => $stat->assigned_staff_id,
                'staff_name' => $stat->assignedStaff?->name,
                'total_leads' => $stat->total,
            ];
        })->toArray();
    }

    /**
     * Reset round robin counter
     */
    public function resetRoundRobin(int $configId): void
    {
        $cacheKey = "lead_distribution:round_robin:{$configId}";
        Cache::forget($cacheKey);

        Log::info('Round robin counter reset', [
            'config_id' => $configId,
        ]);
    }
}
