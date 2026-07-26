<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeTarget extends Model
{
    protected $fillable = [
        'notice_id',
        'target_type',
        'target_id',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }
}
