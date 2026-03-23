<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelesaleLeadCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public ?string $duplicateKey = null,
        public bool $isDuplicate = false,
        public int $groupCount = 1,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('telesale.leads.' . $this->customer->organization_id),
            new Channel('telesale.dashboard.' . $this->customer->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LeadCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'organization_id' => $this->customer->organization_id,
            'customer_name' => $this->customer->username,
            'phone' => $this->customer->phone,
            'duplicate_key' => $this->duplicateKey,
            'is_duplicate' => $this->isDuplicate,
            'group_count' => $this->groupCount,
            'created_at' => optional($this->customer->created_at)->toIso8601String(),
        ];
    }
}
