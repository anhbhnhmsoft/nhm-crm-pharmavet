<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_type',
        'filters_json',
        'row_count',
        'status',
        'file_path',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
