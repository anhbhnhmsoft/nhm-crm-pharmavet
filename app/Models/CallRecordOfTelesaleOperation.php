<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Model;

class CallRecordOfTelesaleOperation extends Model
{
    use SoftDeletes, GenerateIdSnowflake;

    protected $table = 'call_record_of_telesale_operations';

    protected $fillable = [
        'customer_id',
        'staff_id',
        'customer_interaction_id',
        'customer_status_log_id',
        'path_record',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customerInteraction()
    {
        return $this->belongsTo(CustomerInteraction::class);
    }

    public function customerStatusLog()
    {
        return $this->belongsTo(CustomerStatusLog::class);
    }
}
