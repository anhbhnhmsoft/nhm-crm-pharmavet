<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class EnsureApiRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth()->user() ?? auth('api')->user();

        if (!$user) {
            throw new UnauthorizedHttpException('Bearer', __('common.error.invalid_or_expired_token'));
        }

        if ($roles !== [] && !in_array((string) $user->role, $roles, true)) {
            throw new AccessDeniedHttpException(__('common.error.403'));
        }

        return $next($request);
    }
}
