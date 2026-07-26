<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PressPrepTurn extends Model
{
    protected $fillable = [
        'press_prep_session_id',
        'turn_index',
        'role',
        'question',
        'user_answer',
        'model_answer',
        'hint_text',
        'coach_note',
        'follow_up',
        'is_follow_up',
        'score_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_follow_up' => 'boolean',
            'score_notes' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PressPrepSession::class, 'press_prep_session_id');
    }
}
