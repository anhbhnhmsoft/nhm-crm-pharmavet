<?php

namespace App\Services\Telesale;

use App\Models\Customer;
use App\Repositories\TelesaleNotificationAggregateRepository;
use Illuminate\Support\Facades\DB;

class LeadNotificationService

{
    public function __construct(
        private TelesaleNotificationAggregateRepository $telesaleNotificationAggregateRepository,
    ) {
    }

    public function aggregateDuplicateLeadNotifications(Customer $customer): array
    {
        $duplicateHash = $this->buildDuplicateHash($customer->phone, $customer->email);
        $isDuplicate = false;
        $groupCount = 1;

        DB::transaction(function () use ($customer, $duplicateHash, &$isDuplicate, &$groupCount): void {
            $customer->update(['duplicate_hash' => $duplicateHash]);

            $aggregate = $this->telesaleNotificationAggregateRepository->query()
                ->where('organization_id', $customer->organization_id)
                ->where('duplicate_hash', $duplicateHash)
                ->lockForUpdate()
                ->first();

            if ($aggregate) {
                $aggregate->lead_count += 1;
                $aggregate->last_customer_id = $customer->id;
                $aggregate->last_notified_at = now();
                $aggregate->save();
                $isDuplicate = true;
                $groupCount = (int) $aggregate->lead_count;

                return;
            }

            $this->telesaleNotificationAggregateRepository->create([
                'organization_id' => $customer->organization_id,
                'duplicate_hash' => $duplicateHash,
                'lead_count' => 1,
                'last_customer_id' => $customer->id,
                'last_notified_at' => now(),
            ]);
        });

        return [
            'duplicate_key' => $duplicateHash,
            'is_duplicate' => $isDuplicate,
            'group_count' => $groupCount,
        ];
    }

    private function buildDuplicateHash(?string $phone, ?string $email): string
    {
        $normalizedPhone = preg_replace('/\D+/', '', (string) $phone);
        $normalizedEmail = strtolower(trim((string) $email));

        return sha1($normalizedPhone . '|' . $normalizedEmail);
    }
}
