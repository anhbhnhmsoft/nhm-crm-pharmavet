<?php

namespace App\Services;

use App\Common\Constants\User\UserRole;
use App\Common\Constants\Customer\BlackListReason;
use App\Common\Constants\User\NotificationType;
use App\Core\Logging;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DebtNotificationService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected UserRepository $userRepository
    ) {}

    public function notifyOverdueDebts(int $thresholdDays = 3): void
    {
        // Mốc 1: Nhắc nợ cơ bản (3 ngày) -> Gửi Sale
        $this->processDebtMobiles(3, 'sale_warning');

        // Mốc 2: Nhắc nợ nghiêm túc (15 ngày) -> Gửi Sale + Khách hàng (nếu nợ KH) / Kế toán (nợ PTGH)
        $this->processDebtMobiles(15, 'serious_warning');

        // Mốc 3: Khóa tài khoản & Cảnh báo đỏ (30 ngày) -> Lock Customer + Gửi Kế toán
        $this->processDebtMobiles(30, 'lock_system');
    }

    /**
     * Xử lý thông báo theo từng mốc thời gian
     */
    protected function processDebtMobiles(int $days, string $level): void
    {
        // 1. Nợ từ PTGH
        $logisticOrders = $this->orderRepository->findOrdersByDebtAge($days, true);
        $this->handleLogisticLevels($logisticOrders, $days, $level);

        // 2. Nợ từ Khách hàng
        $customerOrders = $this->orderRepository->findOrdersByDebtAge($days, false);
        $this->handleCustomerLevels($customerOrders, $days, $level);
    }

    protected function handleLogisticLevels(Collection $orders, int $days, string $level): void
    {
        foreach ($orders as $order) {
            $message = __('order.notification.debt.logistic_message', [
                'code' => $order->code,
                'days' => $days
            ]);

            if ($level === 'sale_warning') {
                $this->notifySale($order, $message);
            } elseif ($level === 'serious_warning' || $level === 'lock_system') {
                $this->notifyAccounting($order, "[Mức $level] $message");
            }
        }
    }

    protected function handleCustomerLevels(Collection $orders, int $days, string $level): void
    {
        foreach ($orders as $order) {
            $message = __('order.notification.debt.customer_message', [
                'customer' => $order->customer?->username,
                'code' => $order->code,
                'days' => $days
            ]);

            if ($level === 'sale_warning') {
                $this->notifySale($order, $message);
            } elseif ($level === 'serious_warning') {
                $this->notifySale($order, $message);
                $this->sendEmailNotificationToCustomer($order->customer, $order, $message);
            } elseif ($level === 'lock_system') {
                $this->lockCustomer($order->customer, $order);
                $this->notifyAccounting($order, __('debt.lock_customer_message', [
                    'customer' => $order->customer?->username,
                    'code' => $order->code,
                    'days' => $days
                ]));
            }
        }
    }

    /**
     * Khóa khách hàng (Đưa vào Blacklist)
     */
    protected function lockCustomer(?Customer $customer, Order $order): void
    {
        if (!$customer) return;

        DB::transaction(function () use ($customer, $order) {
            // Kiểm tra xem đã trong blacklist chưa
            if (!$customer->blackList()->exists()) {
                $customer->blackList()->create([
                    'user_id' => null,
                    'reason' => BlackListReason::OVERDUE_DEBT_30->value,
                    'note' => __('debt.lock_customer_note', [
                        'code' => $order->code,
                    ]),
                ]);
                
                Log::info("Customer Locked automatically: {$customer->username} due to order {$order->code}");
            }
        });
    }

    protected function notifyAccounting(Order $order, string $message): void
    {
        // Gửi thông báo cho Super Admin hoặc những người có role Admin/Kế toán trong cùng tổ chức
        // Gửi thông báo cho Super Admin hoặc những người có role Admin/Kế toán trong cùng tổ chức
        $this->userRepository->query()
            ->where(function ($query) use ($order) {
                $query->where('role', UserRole::SUPER_ADMIN->value)
                    // 2. Admin hoặc Accounting trong cùng Tổ chức
                    ->orWhere(function ($subQuery) use ($order) {
                        $subQuery->whereIn('role', [UserRole::ADMIN->value, UserRole::ACCOUNTING->value])
                            ->where('organization_id', $order->organization_id);
                    });
            })
            ->each(function (User $user) use ($order, $message) {
                $this->sendDatabaseNotification($user, $order, $message);
            });
    }

    protected function sendEmailNotificationToCustomer(?Customer $customer, Order $order, string $message): void
    {
        if (!$customer || !$customer->email) return;

        try {
            Mail::raw($message, function ($mail) use ($customer, $order) {
                $mail->to($customer->email)
                     ->subject(__('order.notification.debt.email_subject', ['code' => $order->code]));
            });
        } catch (\Exception $e) {
            Logging::error("Customer Debt Email Error ({$customer->email}): " . $e->getMessage());
        }
    }

    /**
     * Gửi thông báo cho Nhân viên (Sale) phụ trách đơn hàng
     */
    protected function notifySale(Order $order, string $message): void
    {
        $sale = $order->createdBy;
        if (!$sale) {
            return;
        }

        // 1. Lưu thông báo vào Database
        $this->sendDatabaseNotification($sale, $order, $message);

        // 2. Gửi Email nhắc nhở
        $this->sendEmailNotification($sale, $order, $message);
    }

    /**
     * Tạo bản ghi thông báo trong Database theo chuẩn Filament + Enum
     */
    protected function sendDatabaseNotification(User $user, Order $order, string $message): void
    {
        $notification = Notification::make()
            ->title(__('order.notification.debt.warning_title'))
            ->body($message)
            ->warning()
            ->actions([
                Action::make('view')
                    ->label(__('order.notification.debt.view_order'))
                    ->url(fn() => route('filament.admin.resources.orders.edit', ['record' => $order->id]))
            ]);

        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => NotificationType::DEBT_REMINDER->value,
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
            'data' => json_encode($notification->toArray()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Gửi email thuần cho Sale
     */
    protected function sendEmailNotification(User $user, Order $order, string $message): void
    {
        if (!$user->email) {
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($user, $order) {
                $mail->to($user->email)
                     ->subject(__('order.notification.debt.email_subject', ['code' => $order->code]));
            });
        } catch (\Exception $e) {
            Logging::error("Debt Email Error ({$user->email}): " . $e->getMessage());
        }
    }
}
