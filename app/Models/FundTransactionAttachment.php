<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransactionAttachment extends Model
{
    protected $table = 'fund_transaction_attachments';

    protected $fillable = [
        'fund_transaction_id',
        'version',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FundTransaction::class, 'fund_transaction_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
