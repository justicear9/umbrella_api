<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    protected $fillable = [
        'title',
        'body',
        'link_url',
        'priority',
        'status',
        'audience_mode',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NoticeTarget::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NoticeUser::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return list<int> */
    public function targetIds(): array
    {
        return $this->targets->pluck('target_id')->map(fn ($id) => (int) $id)->all();
    }
}
