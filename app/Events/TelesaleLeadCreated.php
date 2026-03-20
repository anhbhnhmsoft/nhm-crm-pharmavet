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

    public function __construct(public Customer $customer)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('telesale.leads.' . $this->customer->organization_id),
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
            'created_at' => optional($this->customer->created_at)->toIso8601String(),
        ];
    }
}
