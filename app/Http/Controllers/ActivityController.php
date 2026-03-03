<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
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
            if (Session::has('impersonator_id')) {
                return response()->json(['status' => 'impersonating_ok']);
            }

            Session::put('last_activity', time());
            return response()->json(['status' => 'ok']);
        }

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

            Session::forget('last_activity');
            Session::forget('user_leaving');

            return response()->json(['status' => 'logged_out']);
        }
        return response()->json(['status' => 'ok']);
    }

    /**
     * Xử lý khi user rời khỏi trang (đóng browser/tab) thông qua sendBeacon.
     */
    public function userLeaving(Request $request)
    {
        if (Auth::check()) {
            Session::put('last_activity', time());
            Session::put('user_leaving', true);
        }
        return response()->noContent();
    }
}
