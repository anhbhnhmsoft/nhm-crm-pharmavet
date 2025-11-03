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

    public function loginAs(Request $request, User $user): RedirectResponse | Response
    {
        // 1. Kiểm tra quyền của người dùng hiện tại (Super Admin)
        if (!Auth::user()->hasRole(\App\Common\Constants\User\UserRole::SUPER_ADMIN)) {
            abort(403, __('common.error.403'));
        }

        // 2. Không cho phép Super Admin đăng nhập vào chính tài khoản của mình
        if (Auth::id() == $user->id) {
            abort(403, __('common.error.400'));
        }

        // Kiểm tra user mục tiêu có bị vô hiệu hóa không
        if ($user->disable) {
            abort(403, __('common.error.403'));
        }

        // 3. Lưu ID của Super Admin
        $impersonatorId = Auth::id(); // Lấy ID của Super Admin trước khi logout

        // Lưu ID của Super Admin vào Session để có thể trở về sau
        Session::put('impersonator_id', $impersonatorId);

        // 4. Đăng nhập với tài khoản của người dùng được chọn
        Auth::loginUsingId($user->id, true); // Thêm 'true' để ghi nhớ session lâu hơn (tùy chọn)

        // 5. Chuyển hướng và thông báo thành công
        return redirect()->to('/');
    }

    // route để Super Admin thoát khỏi trạng thái impersonation
    public function leave(): RedirectResponse | Response
    {
        // Lấy impersonator id (không xóa ngay)
        $impersonatorId = Session::get('impersonator_id');

        if (! $impersonatorId) {
            return redirect('/')->with('error', 'Bạn không ở trong chế độ giả mạo.');
        }

        // Lưu local variable trước khi thao tác session
        $impersonator = User::find($impersonatorId);

        // Logout khỏi tài khoản hiện tại (user bị impersonate)
        Auth::logout();

        // Xóa key impersonator khỏi session (đã dùng xong)
        Session::forget('impersonator_id');

        // Nếu impersonator tồn tại, đăng nhập lại
        if ($impersonator) {
            Auth::loginUsingId($impersonator->id);
            session()->regenerate(); // regenerate lại session sau login
            return redirect('/admin')->with('success', 'Đã trở về tài khoản Super Admin.');
        }

        // Nếu không tìm thấy impersonator, bắt người dùng đăng nhập lại
        return redirect()->route('filament.auth.login');
    }
}
