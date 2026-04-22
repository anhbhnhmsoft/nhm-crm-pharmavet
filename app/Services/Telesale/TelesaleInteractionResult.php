<?php

namespace App\Services\Telesale;

use App\Common\Constants\Customer\ReasonInteraction;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\CustomerStatusLog;
use Carbon\CarbonInterface;

readonly class TelesaleInteractionResult
{
    public function __construct(
        public Customer $customer,
        public CustomerInteraction $interaction,
        public CustomerStatusLog $statusLog,
        public ReasonInteraction $reason,
        public int $fromStatus,
        public int $toStatus,
        public ?CarbonInterface $nextActionAt,
        public string $message,
    ) {
    }
}
