<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTicketDetail extends Model
{
    use HasFactory;

    protected $table = 'inventory_ticket_details';

    protected $fillable = [
        'inventory_ticket_id',
        'product_id',
        'quantity',
        'current_quantity',
    ];

    public function ticket()
    {
        return $this->belongsTo(InventoryTicket::class, 'inventory_ticket_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
