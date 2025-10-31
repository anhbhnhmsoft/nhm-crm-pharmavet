<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Services\AuthService;

class HandleUserLogout
{
    public function __construct(protected AuthService $authService) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        // Đảm bảo có user
        if ($user) {
            $this->authService->handleLogoutUser($user);
        }
    }
}
