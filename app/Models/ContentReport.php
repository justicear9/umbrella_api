<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReport extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    /** Canonical report categories (stored in `reason`). */
    public const REASONS = [
        'sexual_explicit' => 'Sexual / explicit',
        'harassment_hate' => 'Harassment / hate',
        'threats_violence' => 'Threats / violence',
        'spam' => 'Spam',
        'fraud_scam' => 'Fraud / scam',
        'misinformation' => 'Misinformation',
        'other' => 'Other',
    ];

    public static function reasonLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        return self::REASONS[$code] ?? $code;
    }

    protected $fillable = [
        'reporter_id',
        'room_message_id',
        'reported_user_id',
        'reason',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(RoomMessage::class, 'room_message_id')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
