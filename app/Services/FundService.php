<?php

namespace App\Services;

use App\Common\Constants\Organization\FundLockAction;
use App\Common\Constants\Organization\FundLockScope;
use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Core\ServiceReturn;
use App\Models\Fund;
use App\Models\FundLockRule;
use App\Models\FundTransaction;
use App\Models\User;
use App\Repositories\FundLockAuditRepository;
use App\Repositories\FundLockRuleRepository;
use App\Repositories\FundTransactionAttachmentRepository;
use App\Repositories\FundTransactionRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FundService
{

    public function __construct(
        protected FundTransactionRepository $fundTransactionRepository,
        protected FundLockRuleRepository $fundLockRuleRepository,
        protected FundLockAuditRepository $fundLockAuditRepository,
        protected FundTransactionAttachmentRepository $fundTransactionAttachmentRepository,
    ) {
    }
    public function createTransaction(Fund $fund, array $data, ?User $actor = null): ServiceReturn
    {
        $actor ??= Auth::user();

        if ($actor && !$this->canPerformAction($fund, $actor, FundLockAction::ADD)) {
            return ServiceReturn::error($this->getDeniedMessage(FundLockAction::ADD));
        }

        try {
            return DB::transaction(function () use ($fund, $data, $actor) {
                $transactionDate = (string) ($data['transaction_date'] ?? now()->toDateString());
                $type = (int) ($data['type'] ?? 0);
                $amount = (float) ($data['amount'] ?? 0);

                if ($amount <= 0) {
                    return ServiceReturn::error(__('accounting.fund.notifications.invalid_amount'));
                }

                if (!in_array($type, [FundTransactionType::DEPOSIT->value, FundTransactionType::WITHDRAW->value], true)) {
                    return ServiceReturn::error(__('accounting.fund.notifications.invalid_type'));
                }

                $this->assertNoNegativeBalance($fund, $type, $amount);

                $transaction = $fund->transactions()->create([
                    'type' => $type,
                    'transaction_date' => $transactionDate,
                    'amount' => $amount,
                    'counterparty_name' => $data['counterparty_name'] ?? null,
                    'currency' => $data['currency'] ?? ($fund->currency ?? 'VND'),
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'amount_base' => $this->toBaseAmount(
                        amount: $amount,
                        currency: (string) ($data['currency'] ?? ($fund->currency ?? 'VND')),
                        exchangeRate: $data['exchange_rate'] ?? null
                    ),
                    'description' => $data['description'] ?? '',
                    'purpose' => $data['purpose'] ?? null,
                    'note' => $data['note'] ?? null,
                    'status' => FundTransactionStatus::COMPLETED->value,
                    'transaction_code' => 'FT' . now()->format('YmdHis') . random_int(100, 999),
                    'updated_by' => $actor?->id,
                ]);

                $this->syncAttachmentVersions(
                    transaction: $transaction,
                    filePaths: Arr::wrap($data['attachments'] ?? []),
                    actorId: $actor?->id
                );

                $this->recalculateFundBalances($fund);

                return ServiceReturn::success($transaction);
            });
        } catch (\RuntimeException $e) {
            return ServiceReturn::error($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Fund transaction error: ' . $e->getMessage());
            return ServiceReturn::error(__('error.common_error_server'));
        }
    }

    public function updateTransaction(FundTransaction $transaction, array $data, ?User $actor = null): ServiceReturn
    {
        $actor ??= Auth::user();
        $fund = $transaction->fund;

        if (!$fund) {
            return ServiceReturn::error(__('accounting.fund.notifications.fund_not_found'));
        }

        if ($actor && !$this->canPerformAction($fund, $actor, FundLockAction::EDIT)) {
            return ServiceReturn::error($this->getDeniedMessage(FundLockAction::EDIT));
        }

        try {
            return DB::transaction(function () use ($transaction, $data, $actor, $fund) {
                $payload = [
                    'transaction_date' => $data['transaction_date'] ?? $transaction->transaction_date?->toDateString(),
                    'type' => (int) ($data['type'] ?? $transaction->type),
                    'amount' => (float) ($data['amount'] ?? $transaction->amount),
                    'counterparty_name' => $data['counterparty_name'] ?? $transaction->counterparty_name,
                    'currency' => $data['currency'] ?? $transaction->currency ?? ($fund->currency ?? 'VND'),
                    'exchange_rate' => $data['exchange_rate'] ?? $transaction->exchange_rate,
                    'description' => $data['description'] ?? $transaction->description,
                    'purpose' => $data['purpose'] ?? $transaction->purpose,
                    'note' => $data['note'] ?? $transaction->note,
                    'updated_by' => $actor?->id,
                ];

                $payload['amount_base'] = $this->toBaseAmount(
                    amount: (float) $payload['amount'],
                    currency: (string) $payload['currency'],
                    exchangeRate: $payload['exchange_rate']
                );

                $transaction->update($payload);

                $this->syncAttachmentVersions(
                    transaction: $transaction->fresh(),
                    filePaths: Arr::wrap($data['attachments'] ?? []),
                    actorId: $actor?->id
                );

                $this->recalculateFundBalances($fund->fresh());

                return ServiceReturn::success($transaction->fresh());
            });
        } catch (\RuntimeException $e) {
            return ServiceReturn::error($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Fund transaction update error: ' . $e->getMessage());
            return ServiceReturn::error(__('error.common_error_server'));
        }
    }

    public function deleteTransaction(FundTransaction $transaction, ?User $actor = null): ServiceReturn
    {
        $actor ??= Auth::user();
        $fund = $transaction->fund;

        if (!$fund) {
            return ServiceReturn::error(__('accounting.fund.notifications.fund_not_found'));
        }

        if ($actor && !$this->canPerformAction($fund, $actor, FundLockAction::DELETE)) {
            return ServiceReturn::error($this->getDeniedMessage(FundLockAction::DELETE));
        }

        try {
            DB::transaction(function () use ($transaction, $fund) {
                $transaction->delete();
                $this->recalculateFundBalances($fund->fresh());
            });

            return ServiceReturn::success(message: __('common.notification.success'));
        } catch (\RuntimeException $e) {
            return ServiceReturn::error($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Fund transaction delete error: ' . $e->getMessage());
            return ServiceReturn::error(__('error.common_error_server'));
        }
    }

    public function canPerformAction(Fund $fund, User $user, FundLockAction $action): bool
    {
        if ($fund->is_locked) {
            return false;
        }

        $rules = FundLockRule::query()
            ->where('fund_id', $fund->id)
            ->where('action', $action->value)
            ->where('is_locked', true)
            ->get();

        if ($rules->isEmpty()) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id')->toArray();

        foreach ($rules as $rule) {
            if ($rule->scope_type === FundLockScope::GLOBAL->value) {
                return false;
            }

            if ($rule->scope_type === FundLockScope::USER->value && (int) $rule->user_id === (int) $user->id) {
                return false;
            }

            if ($rule->scope_type === FundLockScope::TEAM->value && in_array((int) $rule->team_id, $teamIds, true)) {
                return false;
            }
        }

        return true;
    }

    public function upsertLockRule(Fund $fund, array $payload, ?User $actor = null): ServiceReturn
    {
        $actor ??= Auth::user();

        try {
            $action = (string) ($payload['action'] ?? '');
            $scopeType = (string) ($payload['scope_type'] ?? FundLockScope::GLOBAL->value);

            if (!in_array($action, array_keys(FundLockAction::options()), true)) {
                return ServiceReturn::error(__('accounting.fund_lock.notifications.invalid_action'));
            }

            if (!in_array($scopeType, array_keys(FundLockScope::options()), true)) {
                return ServiceReturn::error(__('accounting.fund_lock.notifications.invalid_scope'));
            }

            $isLocked = (bool) ($payload['is_locked'] ?? true);
            $rules = [];

            if ($scopeType === FundLockScope::USER->value) {
                $userIds = array_values(array_unique(array_map('intval', Arr::wrap($payload['user_ids'] ?? $payload['user_id'] ?? []))));
                if (empty($userIds)) {
                    return ServiceReturn::error(__('accounting.fund_lock.notifications.user_required'));
                }

                foreach ($userIds as $userId) {
                    $rules[] = FundLockRule::query()->updateOrCreate(
                        [
                            'fund_id' => $fund->id,
                            'action' => $action,
                            'scope_type' => $scopeType,
                            'user_id' => $userId,
                            'team_id' => null,
                        ],
                        [
                            'is_locked' => $isLocked,
                            'updated_by' => $actor?->id,
                            'created_by' => $actor?->id,
                        ]
                    );
                }
            } elseif ($scopeType === FundLockScope::TEAM->value) {
                $teamIds = array_values(array_unique(array_map('intval', Arr::wrap($payload['team_ids'] ?? $payload['team_id'] ?? []))));
                if (empty($teamIds)) {
                    return ServiceReturn::error(__('accounting.fund_lock.notifications.team_required'));
                }

                foreach ($teamIds as $teamId) {
                    $rules[] = FundLockRule::query()->updateOrCreate(
                        [
                            'fund_id' => $fund->id,
                            'action' => $action,
                            'scope_type' => $scopeType,
                            'user_id' => null,
                            'team_id' => $teamId,
                        ],
                        [
                            'is_locked' => $isLocked,
                            'updated_by' => $actor?->id,
                            'created_by' => $actor?->id,
                        ]
                    );
                }
            } else {
                $rules[] = FundLockRule::query()->updateOrCreate(
                    [
                        'fund_id' => $fund->id,
                        'action' => $action,
                        'scope_type' => $scopeType,
                        'user_id' => null,
                        'team_id' => null,
                    ],
                    [
                        'is_locked' => $isLocked,
                        'updated_by' => $actor?->id,
                        'created_by' => $actor?->id,
                    ]
                );
            }

            foreach ($rules as $rule) {
                $this->auditLockRule($fund, [
                    'action' => $action,
                    'scope_type' => $scopeType,
                    'is_locked' => (bool) $rule->is_locked,
                    'target_user_id' => $rule->user_id,
                    'target_team_id' => $rule->team_id,
                ], $actor?->id);
            }

            return ServiceReturn::success($rules);
        } catch (Throwable $e) {
            Log::error('Fund lock rule upsert error: ' . $e->getMessage());
            return ServiceReturn::error(__('error.common_error_server'));
        }
    }

    public function lockFund(Fund $fund, ?User $actor = null): ServiceReturn
    {
        try {
            $fund->update(['is_locked' => true]);
            $this->auditLockRule($fund, [
                'action' => FundLockAction::EDIT->value,
                'scope_type' => FundLockScope::GLOBAL->value,
                'is_locked' => true,
            ], $actor?->id ?? Auth::id());

            return ServiceReturn::success(message: __('accounting.fund.notifications.locked'));
        } catch (Throwable $e) {
            return ServiceReturn::error(__('accounting.fund.notifications.lock_failed'));
        }
    }

    public function unlockFund(Fund $fund, ?User $actor = null): ServiceReturn
    {
        try {
            $fund->update(['is_locked' => false]);
            $this->auditLockRule($fund, [
                'action' => FundLockAction::EDIT->value,
                'scope_type' => FundLockScope::GLOBAL->value,
                'is_locked' => false,
            ], $actor?->id ?? Auth::id());

            return ServiceReturn::success(message: __('accounting.fund.notifications.unlocked'));
        } catch (Throwable $e) {
            return ServiceReturn::error(__('accounting.fund.notifications.unlock_failed'));
        }
    }

    public function recalculateFundBalances(Fund $fund): void
    {
        DB::transaction(function () use ($fund) {
            $running = 0.0;
            $transactions = $fund->transactions()
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            foreach ($transactions as $transaction) {
                $signedAmount = $this->signedAmountByType((int) $transaction->type, (float) $transaction->amount);
                $running += $signedAmount;
                if ($running < 0) {
                    throw new \RuntimeException(__('accounting.fund.notifications.insufficient_balance'));
                }

                FundTransaction::withoutEvents(function () use ($transaction, $running) {
                    $transaction->update([
                        'balance_after' => $running,
                    ]);
                });
            }

            $fund->update(['balance' => $running]);
        });
    }

    protected function assertNoNegativeBalance(Fund $fund, int $type, float $amount): void
    {
        if ($type !== FundTransactionType::WITHDRAW->value) {
            return;
        }

        $fund->refresh();
        $wouldBe = (float) $fund->balance - $amount;
        if ($wouldBe < 0) {
            throw new \RuntimeException(__('accounting.fund.notifications.insufficient_balance'));
        }
    }

    protected function signedAmountByType(int $type, float $amount): float
    {
        if ($type === FundTransactionType::DEPOSIT->value) {
            return $amount;
        }

        return -$amount;
    }

    protected function toBaseAmount(float $amount, string $currency, mixed $exchangeRate = null): float
    {
        $normalizedCurrency = strtoupper($currency);
        if ($normalizedCurrency === 'VND') {
            return round($amount, 2);
        }

        $rate = (float) ($exchangeRate ?? 0);
        if ($rate <= 0) {
            return round($amount, 2);
        }

        return round($amount * $rate, 2);
    }

    protected function syncAttachmentVersions(FundTransaction $transaction, array $filePaths, ?int $actorId = null): void
    {
        foreach ($filePaths as $filePath) {
            if (!is_string($filePath) || $filePath === '') {
                continue;
            }

            $exists = $transaction->attachments()->where('file_path', $filePath)->exists();
            if ($exists) {
                continue;
            }

            $latestVersion = (int) ($transaction->attachments()->max('version') ?? 0);
            $this->fundTransactionAttachmentRepository->create([
                'fund_transaction_id' => $transaction->id,
                'version' => $latestVersion + 1,
                'file_path' => $filePath,
                'original_name' => basename($filePath),
                'uploaded_by' => $actorId,
                'uploaded_at' => now(),
            ]);
        }
    }

    protected function auditLockRule(Fund $fund, array $payload, ?int $actorId = null): void
    {
        $this->fundLockAuditRepository->create([
            'fund_id' => $fund->id,
            'action' => (string) ($payload['action'] ?? FundLockAction::EDIT->value),
            'is_locked' => (bool) ($payload['is_locked'] ?? true),
            'scope_type' => (string) ($payload['scope_type'] ?? FundLockScope::GLOBAL->value),
            'target_user_id' => $payload['target_user_id'] ?? null,
            'target_team_id' => $payload['target_team_id'] ?? null,
            'metadata_json' => $payload,
            'changed_by' => $actorId,
            'changed_at' => now(),
        ]);
    }

    public function getDeniedMessage(FundLockAction $action): string
    {
        return __('accounting.fund_lock.notifications.denied_action', [
            'action' => $action->label(),
        ]);
    }
}
