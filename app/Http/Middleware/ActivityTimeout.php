<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class ActivityTimeout
{
    public function __construct(protected AuthService $authService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = Session::get('last_activity');
            $timeoutSeconds = 15 * 60; // 15 phút = 900 giây

            // Kiểm tra: Nếu đã quá 15 phút kể từ hoạt động cuối cùng
            if ($lastActivity && (time() - $lastActivity > $timeoutSeconds)) {

                $user = Auth::user();
                Auth::logout();
                $this->authService->handleLogoutUser($user);

                // Chuyển hướng với thông báo
                return redirect()->route('filament.auth.login')->withErrors([
                    'session_timeout' => __('filament.login.error.activity_timeout')
                ]);
            }

            // Nếu vẫn hoạt động, cập nhật thời điểm cuối cùng trong Session
            Session::put('last_activity', time());
        }
        return $next($request);
    }
}
