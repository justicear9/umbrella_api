<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomMessage extends Model
{
    use SoftDeletes;

    public const KIND_USER = 'user';

    public const KIND_AI = 'ai';

    protected $fillable = [
        'chat_room_id',
        'user_id',
        'kind',
        'body',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(RoomMessageMention::class);
    }
}
