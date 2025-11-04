<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ActivityController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    /**
     * Cập nhật thời điểm hoạt động cuối cùng của người dùng.
     */
    public function heartbeat(Request $request)
    {
        if (Auth::check()) {
            // Nếu Super Admin đang ở chế độ Impersonation, bỏ qua việc cập nhật Session timeout
            if (Session::has('impersonator_id')) {
                // Vẫn trả về OK để activity-tracker.js không bị lỗi 401
                return response()->json(['status' => 'impersonating_ok']);
            }

            Session::put('last_activity', time());
            return response()->json(['status' => 'ok']);
        }

        // Trả về 401 nếu không có Auth
        return response()->json(['status' => 'unauthorized'], 401);
    }

    /**
     * Xử lý logout chủ động (hoặc do client/cảnh báo timeout gọi).
     */
    public function activityLogout(Request $request)
    {
        $impersonator = Session::put('impersonator_id');
        if (Auth::check() && $impersonator) {
            $user = Auth::user();
            Auth::logout();
            $this->authService->handleLogoutUser($user);

            // Dọn dẹp các session liên quan đến hoạt động sau khi user logout
            Session::forget('last_activity');
            Session::forget('user_leaving');

            return response()->json(['status' => 'logged_out']);
        }
        // Nếu user đã logout rồi, trả về ok
        return response()->json(['status' => 'ok']);
    }

    /**
     * Xử lý khi user rời khỏi trang (đóng browser/tab) thông qua sendBeacon.
     */
    public function userLeaving(Request $request)
    {
        if (Auth::check()) {
            // Cập nhật last_activity để kéo dài phiên thêm một chu kỳ timeout
            Session::put('last_activity', time());
            Session::put('user_leaving', true);
        }
        // Luôn trả về 204 No Content cho navigator.sendBeacon
        return response()->noContent();
    }
}
