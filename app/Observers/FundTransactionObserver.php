<?php

namespace App\Observers;

use App\Common\Constants\Organization\FundLockAction;
use App\Models\FundTransaction;
use App\Services\FundService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FundTransactionObserver
{
    public function __construct(
        protected FundService $service,
    ) {}

    public function creating(FundTransaction $transaction): void
    {
        $this->guardByLockRule($transaction, FundLockAction::ADD);
    }

    public function updating(FundTransaction $transaction): void
    {
        $this->guardByLockRule($transaction, FundLockAction::EDIT);
    }

    public function deleting(FundTransaction $transaction): void
    {
        $this->guardByLockRule($transaction, FundLockAction::DELETE);
    }

    protected function guardByLockRule(FundTransaction $transaction, FundLockAction $action): void
    {
        $fund = $transaction->fund()->first();
        $user = Auth::user();

        if (!$fund || !$user) {
            return;
        }

        if (!$this->service->canPerformAction($fund, $user, $action)) {
            throw new AccessDeniedHttpException($this->service->getDeniedMessage($action));
        }
    }
}
