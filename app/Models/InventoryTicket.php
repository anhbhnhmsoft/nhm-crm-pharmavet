<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $date = $model->approved_at ?: $model->created_at ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                // Chỉ khóa nếu là phiếu Nhập hoàn (ảnh hưởng doanh thu) hoặc nếu user muốn khóa toàn bộ kho
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });

        static::deleting(function ($model) {
            $date = $model->approved_at ?: $model->created_at ?: now();
            if (AccountingPeriod::isClosed($model->organization_id, $date->month, $date->year)) {
                throw new \Exception(__('accounting.accounting_period.period_closed'));
            }
        });
    }

    protected $table = 'inventory_tickets';

    protected $fillable = [
        'organization_id',
        'code',
        'type',
        'order_id',
        'is_sales_return',
        'status',
        'warehouse_id',
        'source_warehouse_id',
        'target_warehouse_id',
        'note',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function details()
    {
        return $this->hasMany(InventoryTicketDetail::class);
    }
}
