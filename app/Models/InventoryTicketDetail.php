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
        'unit_price',
        'batch_no',
        'expired_at',
        'bin_location_id',
        'current_quantity',
    ];

    protected $casts = [
        'expired_at' => 'date',
    ];

    public function ticket()
    {
        return $this->belongsTo(InventoryTicket::class, 'inventory_ticket_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function binLocation()
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_location_id');
    }
}
