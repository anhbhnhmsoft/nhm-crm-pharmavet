<?php

namespace App\Http\Middleware;

use App\Services\Integrations\FacebookWebhookSignatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ValidateFacebookWebhookSignature
{
    public function __construct(protected FacebookWebhookSignatureService $signatureService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if (!$this->signatureService->isValid($signature, $request->getContent())) {
            throw new AccessDeniedHttpException(__('messages.meta_business.error.invalid_signature'));
        }

        return $next($request);
    }
}
