<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_thread_id',
        'role',
        'content',
        'citations',
        'chart',
        'footnotes',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'chart' => 'array',
            'footnotes' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }
}
