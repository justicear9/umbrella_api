<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    protected $fillable = [
        'slug',
        'name',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(RoomMessage::class);
    }

    public static function national(): self
    {
        return static::query()->where('slug', 'national')->firstOrFail();
    }
}
