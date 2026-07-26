<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomMessageMention extends Model
{
    public const TYPE_COMRADE = 'comrade';

    public const TYPE_CONSTITUENCY = 'constituency';

    protected $fillable = [
        'room_message_id',
        'mention_type',
        'constituency_id',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(RoomMessage::class, 'room_message_id');
    }

    public function constituency(): BelongsTo
    {
        return $this->belongsTo(Constituency::class);
    }
}
