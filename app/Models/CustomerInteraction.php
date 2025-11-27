<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    use HasFactory;

    protected $table = 'customer_interactions';

    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'direction',
        'status',
        'duration',
        'content',
        'metadata',
        'interacted_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'interacted_at' => 'datetime',
        'duration' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeCalls($query)
    {
        return $query->where('type', 'call');
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    public function scopeEmails($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeNotes($query)
    {
        return $query->where('type', 'note');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('interacted_at', '>=', now()->subDays($days));
    }
}
