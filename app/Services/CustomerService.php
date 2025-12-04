<?php

namespace App\Services;

use App\Repositories\LeadDistributionConfigRepository;
use App\Repositories\UserAssignedStaffRepository;
use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Repositories\CustomerRepository;
use App\Common\Constants\StatusProgress;
use App\Common\Constants\Team\TeamType;
use App\Jobs\SingleDataDistributionJob;
use App\Repositories\UserRepository;
use App\Core\ServiceReturn;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CustomerService
{

    public function __construct(
        protected CustomerRepository $customerRepository,
        protected LeadDistributionConfigRepository $leadDistributionConfigRepository,
        protected UserAssignedStaffRepository $userAssignedStaffRepository,
        protected UserRepository $userRepository
    ) {}

    public function createCustomerFromTelesaleOperation($data): ServiceReturn
    {
        DB::beginTransaction();
        try {
            $checkCustomer = $this->customerRepository->query()->where(function ($q) use ($data) {
                $q->where('phone', $data['phone']);
                if (!empty($data['email'])) {
                    $q->orWhere('email', $data['email']);
                }
            })->first();

            if ($checkCustomer) {

                $order = $checkCustomer->orders()->where('status', StatusProgress::COMPLETED->value)->first();
                if ($order) {
                    $customer = $this->customerRepository->create([
                        'username' => $data['username'],
                        'phone' => $data['phone'],
                        'email' => $data['email'],
                        'birthday' => $data['birthday'],
                        'address' => $data['address'],
                        'note_temp' => $data['note_temp'],
                        'product_id' => $data['product_id'],
                        'organization_id' => $data['organization_id'],
                        'customer_type' => CustomerType::OLD_CUSTOMER->value,
                        'source' => IntegrationType::MANUAL_DATA->value,
                        'interaction_status' => InteractionStatus::FIRST_CALL->value,

                    ]);
                    DB::commit();
                    $this->initiateDistributionJob($customer->id);
                    return ServiceReturn::success($customer);
                } else {
                    $customer = $this->customerRepository->create([
                        'username' => $data['username'],
                        'phone' => $data['phone'],
                        'email' => $data['email'],
                        'birthday' => $data['birthday'],
                        'address' => $data['address'],
                        'note_temp' => $data['note_temp'],
                        'product_id' => $data['product_id'],
                        'organization_id' => $data['organization_id'],
                        'customer_type' => CustomerType::NEW_DUPLICATE->value,
                        'interaction_status' => InteractionStatus::FIRST_CALL->value,
                        'source' => IntegrationType::MANUAL_DATA->value,

                    ]);
                    DB::commit();
                    $this->initiateDistributionJob($customer->id);
                    return ServiceReturn::success($customer);
                }
            }

            $customer = $this->customerRepository->create([
                'username' => $data['username'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'birthday' => $data['birthday'],
                'address' => $data['address'],
                'note_temp' => $data['note_temp'],
                'product_id' => $data['product_id'],
                'organization_id' => $data['organization_id'],
                'customer_type' => CustomerType::NEW->value,
                'interaction_status' => InteractionStatus::FIRST_CALL->value,
                'source' => IntegrationType::MANUAL_DATA->value,
            ]);

            DB::commit();
            $this->initiateDistributionJob($customer->id);
            return ServiceReturn::success($customer);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Create customer from telesale operation error: ' . $th->getMessage());
            return ServiceReturn::error(__('message_service.error.create_customer_from_telesale') . 'Create customer from telesale operation error: ' . $th->getMessage());
        }
    }

    public function distributionSingleCustomer($customerId)
    {
        try {
            $customer = $this->customerRepository->query()->find($customerId);
            if (!$customer) {
                return ServiceReturn::error(__('message_service.error.customer_not_found'));
            }

            // Lấy cấu hình phân bổ
            $config = $this->leadDistributionConfigRepository->query()->with(['rules', 'staffSale', 'staffCSKH'])
                ->where(function ($q) use ($customer) {
                    $q->where('organization_id', $customer->organization_id)
                        ->orWhere('product_id', $customer->product_id);
                })
                ->first();

            if (!$config) {
                Log::warning('No active distribution config found', [
                    'customer_id' => $customerId,
                    'organization_id' => $customer->organization_id,
                    'product_id' => $customer->product_id,
                ]);
                return ServiceReturn::error(__('message_service.error.no_active_distribution_config_found'));
            }

            // get staff sale and staff cskh for assign
            $staffSale = $config->staffSale;
            $staffCSKH = $config->staffCSKH;

            // get all rules for assign
            $rules = $config->rules()->where('customer_type', $customer->customer_type)->get();

            if ($rules->isEmpty()) {
                Log::warning('No matching rule found', [
                    'config_id' => $config->id,
                    'customer_type' => $customer->customer_type,
                ]);
                return ServiceReturn::error(__('message_service.error.no_matching_rule_found'));
            }


            DB::beginTransaction();
            try {
                foreach ($rules as $rule) {
                    $targetStaffs = match ($rule->staff_type) {
                        TeamType::CSKH->value => $staffCSKH,
                        TeamType::SALE->value => $staffSale,
                        default => collect(),
                    };

                    if ($targetStaffs->isEmpty()) {
                        continue;
                    }

                    $this->assignCustomerToStaffByDistributionMethod(
                        $customerId,
                        $config->id,
                        $rule,
                        $targetStaffs
                    );
                }

                DB::commit();

                return ServiceReturn::success([
                    'customer' => $customer->fresh(),
                ]);
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Throwable $th) {
            Log::error('Distribution single customer error: ' . $th->getMessage(), [
                'customer_id' => $customerId,
                'trace' => $th->getTraceAsString(),
            ]);
            return ServiceReturn::error(__('message_service.error.distribution_single_customer') . ': ' . $th->getMessage());
        }
    }

    /**
     * Phân bổ customer cho staff theo phương thức phân phối
     */
    public function assignCustomerToStaffByDistributionMethod(
        int $customerId,
        int $configId,
        $rule,
        $staffs
    ) {
        // Validate input
        if ($staffs->isEmpty()) {
            Log::warning("No staffs available for customer distribution", [
                'customer_id' => $customerId,
                'config_id' => $configId
            ]);
            return null;
        }

        $method = $rule->distribution_method;
        $selectedStaff = null;

        try {
            // Chọn staff theo phương thức
            $selectedStaff = match ($method) {
                DistributionMethod::BY_DEFINITION->value =>
                $this->selectByDefinition($staffs),

                DistributionMethod::MOST_RECENT_RECIPIENT->value =>
                $this->selectByRoundRobin($configId, $rule->staff_type, $staffs),

                default => $this->selectRandom($staffs)
            };

            // Fallback nếu không chọn được
            if (!$selectedStaff) {
                $selectedStaff = $this->selectRandom($staffs);
            }

            // Gán customer cho staff
            if ($selectedStaff) {
                $this->assignStaffToCustomer($customerId, $selectedStaff, $method, $rule->staff_type);
            }

            return $selectedStaff;
        } catch (\Throwable $e) {
            Log::error("Error in customer distribution", [
                'customer_id' => $customerId,
                'config_id' => $configId,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to random selection on error
            $fallbackStaff = $this->selectRandom($staffs);
            if ($fallbackStaff) {
                $this->assignStaffToCustomer($customerId, $fallbackStaff, 'fallback', $rule->staff_type);
            }

            return $fallbackStaff;
        }
    }

    /**
     * Phân bổ theo định mức (weighted random)
     */
    private function selectByDefinition($staffs)
    {
        // Lấy tổng weight
        $totalWeight = $staffs->sum(function ($staff) {
            return max(1, $staff->pivot->weight ?? 1); // Đảm bảo weight >= 1
        });

        if ($totalWeight <= 0) {
            Log::warning("Total weight is 0 or negative, falling back to random");
            return $this->selectRandom($staffs);
        }

        // Random theo weight
        $random = rand(1, $totalWeight);
        $current = 0;

        foreach ($staffs as $staff) {
            $weight = max(1, $staff->pivot->weight ?? 1);
            $current += $weight;

            if ($random <= $current) {
                return $staff;
            }
        }

        return $staffs->last();
    }

    /**
     * Phân bổ vòng tròn (Round Robin)
     */
    private function selectByRoundRobin(int $configId, string $staffType, $staffs)
    {
        $cacheKey = "customer_dist_rr_{$configId}_{$staffType}";
        $cacheTTL = 86400 * 30; // 30 days

        // Lấy index hiện tại từ cache với lock để tránh race condition
        $lockKey = "{$cacheKey}_lock";

        $selectedStaff = Cache::lock($lockKey, 5)->block(3, function () use ($cacheKey, $cacheTTL, $staffs) {
            $index = Cache::get($cacheKey, 0);

            // Đảm bảo index hợp lệ
            if ($index >= $staffs->count() || $index < 0) {
                $index = 0;
            }

            // Chọn staff theo index
            $selectedStaff = $staffs->values()->get($index);

            // Cập nhật index cho lần sau
            $nextIndex = ($index + 1) % $staffs->count();
            Cache::put($cacheKey, $nextIndex, $cacheTTL);

            return $selectedStaff;
        });

        return $selectedStaff;
    }

    /**
     * Chọn random staff
     */
    private function selectRandom($staffs)
    {
        if ($staffs->isEmpty()) {
            return null;
        }
        return $staffs->random();
    }

    /**
     * Gán staff cho customer
     */
    private function assignStaffToCustomer(int $customerId, $staff, string $method, ?int $staffType = null)
    {
        try {
            // KHÔNG dùng transaction ở đây vì đã có transaction ở parent method

            // Kiểm tra đã tồn tại chưa
            $exists = $this->userAssignedStaffRepository->query()
                ->where('customer_id', $customerId)
                ->where('staff_id', $staff->id)
                ->exists();

            if ($exists) {
                Log::info("Assignment already exists", [
                    'customer_id' => $customerId,
                    'staff_id' => $staff->id
                ]);
                return;
            }

            // Tạo assignment mới
            $this->userAssignedStaffRepository->create([
                'customer_id' => $customerId,
                'staff_id' => $staff->id,
            ]);

            // Nếu là SALE staff, cập nhật assigned_staff_id cho customer chính
            if ($staffType && $staffType == TeamType::SALE->value) {
                $this->customerRepository->update($customerId, [
                    'assigned_staff_id' => $staff->id
                ]);

                Log::info("Updated customer assigned_staff_id", [
                    'customer_id' => $customerId,
                    'assigned_staff_id' => $staff->id
                ]);
            }

            Log::info("Successfully assigned customer to staff", [
                'customer_id' => $customerId,
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'staff_type' => $staffType,
                'method' => $method
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to assign customer to staff", [
                'customer_id' => $customerId,
                'staff_id' => $staff->id,
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Reset round robin counter (useful for testing or manual reset)
     */
    public function resetRoundRobin(int $configId, string $staffType): void
    {
        $cacheKey = "customer_dist_rr_{$configId}_{$staffType}";
        Cache::forget($cacheKey);

        Log::info("Reset round robin counter", [
            'config_id' => $configId,
            'staff_type' => $staffType
        ]);
    }

    /**
     * Get current round robin index (useful for debugging)
     */
    public function getRoundRobinIndex(int $configId, string $staffType): int
    {
        $cacheKey = "customer_dist_rr_{$configId}_{$staffType}";
        return Cache::get($cacheKey, 0);
    }


    /**
     * Helper: Make and dispatch job
     */
    public function dispatchDistribution(int $customerId, ?string $queue = null): void
    {
        $job = new SingleDataDistributionJob($customerId);

        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);

        Log::info('Customer distribution job dispatched', [
            'customer_id' => $customerId,
            'queue' => $queue ?? 'default',
        ]);
    }

    /**
     * Call dispatchDistribution to queue the job
     */
    public function initiateDistributionJob(int $customerId): ServiceReturn
    {
        try {
            $this->dispatchDistribution($customerId, 'customer-distribution');

            return ServiceReturn::success([
                'message' => 'Customer distribution job queued successfully',
                'customer_id' => $customerId,
            ]);
        } catch (Throwable $th) {
            Log::error('Failed to queue customer distribution', [
                'customer_id' => $customerId,
                'error' => $th->getMessage(),
            ]);
            return ServiceReturn::error(__('message_service.error.queue_customer_distribution') . $th->getMessage());
        }
    }
}
