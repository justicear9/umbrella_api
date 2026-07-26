<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Briefing extends Model
{
    protected $fillable = [
        'document_id',
        'title',
        'category',
        'summary',
        'talking_points',
        'key_stats',
        'watch_outs',
        'citations',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'talking_points' => 'array',
            'key_stats' => 'array',
            'watch_outs' => 'array',
            'citations' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }
}
