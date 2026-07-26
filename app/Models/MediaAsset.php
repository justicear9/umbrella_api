<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaAsset extends Model
{
    protected $fillable = [
        'title',
        'description',
        'kind',
        'original_filename',
        'file_path',
        'mime',
        'byte_size',
        'audience_mode',
        'status',
        'published_at',
        'download_count',
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
        return $this->hasMany(MediaTarget::class);
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
