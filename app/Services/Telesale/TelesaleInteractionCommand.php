<?php

namespace App\Services\Telesale;

use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

readonly class TelesaleInteractionCommand
{
    public function __construct(
        public int $customerId,
        public int $actorId,
        public int $currentStatus,
        public int $reason,
        public ?string $note = null,
        public CarbonInterface|string|null $nextActionAt = null,
        public string $context = 'table_action',
    ) {
    }

    public static function fromArray(Customer $customer, array $data, int $actorId, string $context = 'table_action'): self
    {
        return new self(
            customerId: (int) $customer->getKey(),
            actorId: $actorId,
            currentStatus: (int) $customer->interaction_status,
            reason: (int) ($data['reason'] ?? $data['interaction_reason'] ?? 0),
            note: self::normalizeNote($data['note'] ?? $data['interaction_note'] ?? $data['new_interaction_content'] ?? null),
            nextActionAt: $data['next_action_at'] ?? $data['interaction_next_action_at'] ?? null,
            context: $context,
        );
    }

    public function normalizedNextActionAt(): ?CarbonInterface
    {
        if (blank($this->nextActionAt)) {
            return null;
        }

        if ($this->nextActionAt instanceof CarbonInterface) {
            return $this->nextActionAt;
        }

        return Carbon::parse((string) $this->nextActionAt);
    }

    protected static function normalizeNote(mixed $note): ?string
    {
        if (! is_string($note)) {
            return null;
        }

        $note = trim($note);

        return $note !== '' ? $note : null;
    }
}
