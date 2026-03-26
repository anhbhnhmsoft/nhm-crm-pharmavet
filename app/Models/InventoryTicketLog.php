<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTicketLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_ticket_logs';

    protected $fillable = [
        'inventory_ticket_id',
        'product_id',
        'reason',
        'note',
        'action',
        'old_status',
        'new_status',
        'metadata_json',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function inventoryTicket()
    {
        return $this->belongsTo(InventoryTicket::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class);
    }
}
