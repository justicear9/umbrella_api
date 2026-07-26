<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PressPrepSession extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_by',
        'assigned_at',
        'assignment_note',
        'outing_type',
        'difficulty',
        'interview_mode',
        'voice_preset',
        'topics',
        'hot_issues',
        'question_count',
        'status',
        'current_question',
        'briefing_pack',
        'debrief',
    ];

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'briefing_pack' => 'array',
            'debrief' => 'array',
            'assigned_at' => 'datetime',
        ];
    }

    public function turns(): HasMany
    {
        return $this->hasMany(PressPrepTurn::class)->orderBy('turn_index');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
