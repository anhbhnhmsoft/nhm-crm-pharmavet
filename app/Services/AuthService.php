<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Core\ServiceReturn;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AuthService
{
    protected OrganizationRepository $organizationRepository;
    protected UserRepository $userRepository;

    public function __construct(OrganizationRepository $organizationRepository, UserRepository $userRepository)
    {
        $this->organizationRepository = $organizationRepository;
        $this->userRepository = $userRepository;
    }

    public function handleLoginUser(array $data): ServiceReturn
    {
        try {
            $organize = $this->organizationRepository->query()->firstWhere('code', $data['organization_code']);
            if (!$organize) {
                $error = ValidationException::withMessages(['data.username' => __('filament.login.error.invalid_credentials')]);
                return ServiceReturn::error(__('filament.login.error.invalid_credentials'), $error);
            }

            $user = $this->userRepository->query()
                ->where('organization_id', $organize->id)
                ->where(function ($query) use ($data) {
                    $query->where('email', $data['login_value'])
                        ->orWhere('username', $data['login_value']);
                })
                ->first();

            if (!$user) {
                $error = ValidationException::withMessages(['data.username' => __('filament.login.error.invalid_credentials')]);
                return ServiceReturn::error(__('filament.login.error.invalid_credentials'), $error);
            }

            if (!password_verify($data['password'], $user->password)) {
                $error = ValidationException::withMessages(['data.username' => __('filament.login.error.invalid_credentials')]);
                return ServiceReturn::error(__('filament.login.error.invalid_credentials'), $error);
            }

            $user->last_login_at = now();
            $user->save();

            Auth::login($user);
            Session::put('last_activity', time());
            return ServiceReturn::success($user);
        } catch (ValidationException $exception) {
            return ServiceReturn::error($exception->getMessage(), $exception);
        } catch (Exception $exception) {
            Log::error("Login Error: " . $exception->getMessage());
            return ServiceReturn::error($exception->getMessage(), $exception);
        }
    }

    public function handleLogoutUser($user): ServiceReturn
    {
        try {
            // Lấy thời điểm đăng nhập gần nhất
            $lastLoginAt = $user->last_login_at;
            if ($lastLoginAt) {
                $now = Carbon::now();

                // 1. Tính toán thời gian online của phiên hiện tại (tính bằng giờ)
                $lastActivityTimestamp = Session::pull('last_activity'); // Dùng pull để xóa session sau khi tính toán
                if ($lastActivityTimestamp) {
                    $lastActivityTime = Carbon::createFromTimestamp($lastActivityTimestamp);
                    // Thời gian online là từ last_login_at đến last_activity_time
                    // Đây là thời gian hoạt động thực tế trong phiên (dù đã vượt qua 15 phút timeout)
                    $sessionDurationInSeconds = $lastActivityTime->diffInSeconds($lastLoginAt);
                } else {
                    // Nếu không có last_activity (ví dụ: session đã hết hạn) thì dùng thời điểm logout hiện tại
                    $sessionDurationInSeconds = $now->diffInSeconds($lastLoginAt);
                }

                // Lý do: Nếu user chủ động logout sau 2 giờ, ta cần ghi nhận 2 giờ,
                // không phải chỉ 15 phút. Timeout 15 phút chỉ áp dụng cho middleware/client.
                $sessionDurationInHours = $sessionDurationInSeconds / 3600;

                // 2. Cộng vào tổng online_hours
                $newOnlineHours = $user->online_hours + $sessionDurationInHours;

                // 3. Cập nhật các trường
                $user->update([
                    'last_logout_at' => $now,
                    'online_hours' => $newOnlineHours,
                ]);
            }
            Session::forget('user_leaving');
            return ServiceReturn::success();
        } catch (Exception $exception) {
            Log::error("Logout Error: " . $exception->getMessage());
            return ServiceReturn::error(__('messages.auth.error.logout_failed', ['error' => $exception->getMessage()]), $exception);
        }
    }
}
