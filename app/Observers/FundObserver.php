<?php

namespace App\Observers;

use App\Models\Fund;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FundObserver
{
    public function updating(Fund $fund): void
    {
        if (!$fund->isDirty('balance')) {
            return;
        }

        if ($fund->transactions()->exists()) {
            throw new BadRequestHttpException(__('accounting.fund.notifications.opening_balance_locked'));
        }
    }

    public function deleting(Fund $fund): void
    {
        if ($fund->transactions()->exists()) {
            throw new BadRequestHttpException(__('accounting.fund.notifications.delete_blocked_has_transactions'));
        }
    }
}
