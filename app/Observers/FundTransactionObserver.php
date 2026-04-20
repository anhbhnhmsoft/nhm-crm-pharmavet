<?php

namespace App\Observers;

use App\Common\Constants\Organization\FundLockAction;
use App\Models\FundTransaction;
use App\Services\FundService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FundTransactionObserver
{
    protected static array $openingBalances = [];

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
        $this->rememberOpeningBalance($transaction);
    }

    public function deleting(FundTransaction $transaction): void
    {
        $this->guardByLockRule($transaction, FundLockAction::DELETE);
        $this->rememberOpeningBalance($transaction);
    }

    public function created(FundTransaction $transaction): void
    {
        $this->syncFundBalance($transaction);
    }

    public function updated(FundTransaction $transaction): void
    {
        $this->syncFundBalance($transaction);
    }

    public function deleted(FundTransaction $transaction): void
    {
        $this->syncFundBalance($transaction);
    }

    public function restored(FundTransaction $transaction): void
    {
        $this->syncFundBalance($transaction);
    }

    protected function guardByLockRule(FundTransaction $transaction, FundLockAction $action): void
    {
        $fund = $transaction->fund()->withTrashed()->first();
        $user = Auth::user();

        if (!$fund || !$user) {
            return;
        }

        if (!$this->service->canPerformAction($fund, $user, $action)) {
            throw new AccessDeniedHttpException($this->service->getDeniedMessage($action));
        }
    }

    protected function rememberOpeningBalance(FundTransaction $transaction): void
    {
        $fund = $transaction->fund()->withTrashed()->first();

        if (!$fund) {
            return;
        }

        static::$openingBalances[(int) $fund->id] = $this->service->getOpeningBalance($fund->fresh());
    }

    protected function syncFundBalance(FundTransaction $transaction): void
    {
        $fund = $transaction->fund()->withTrashed()->first();

        if (!$fund) {
            return;
        }

        $openingBalance = static::$openingBalances[(int) $fund->id] ?? null;

        $this->service->recalculateFundBalances($fund->fresh(), $openingBalance);

        unset(static::$openingBalances[(int) $fund->id]);
    }
}
