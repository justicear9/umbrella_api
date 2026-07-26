<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COMMUNICATOR = 'communicator';

    protected $fillable = [
        'role',
        'name',
        'email',
        'password',
        'party_id',
        'date_of_birth',
        'constituency',
        'occupation',
        'comms_level',
        'region_id',
        'constituency_id',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCommunicator(): bool
    {
        return $this->role === self::ROLE_COMMUNICATOR;
    }

    public function hasRealEmail(): bool
    {
        $email = strtolower(trim((string) $this->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return ! str_ends_with($email, '@party.ndc.local');
    }

    public function issueApiToken(): string
    {
        $token = Str::random(60);
        $this->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $token;
    }

    public function clearApiToken(): void
    {
        $this->forceFill(['api_token' => null])->save();
    }

    public function region(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function constituencyRef(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Constituency::class, 'constituency_id');
    }

    public function chatThreads(): HasMany
    {
        return $this->hasMany(ChatThread::class)->orderByDesc('last_message_at');
    }

    public function pressPrepSessions(): HasMany
    {
        return $this->hasMany(PressPrepSession::class)->latest();
    }

    public function devicePushTokens(): HasMany
    {
        return $this->hasMany(DevicePushToken::class);
    }

    public function toPublicArray(): array
    {
        $this->loadMissing(['region:id,name', 'constituencyRef:id,name,region_id']);

        return [
            'id' => $this->id,
            'role' => $this->role,
            'name' => $this->name,
            'email' => $this->email,
            'party_id' => $this->party_id,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'constituency' => $this->constituencyRef?->name ?? $this->constituency,
            'occupation' => $this->occupation,
            'comms_level' => $this->comms_level,
            'region_id' => $this->region_id,
            'constituency_id' => $this->constituency_id,
            'region_name' => $this->region?->name,
            'constituency_name' => $this->constituencyRef?->name ?? $this->constituency,
        ];
    }

    /** Peer-visible profile — no DOB / email. */
    public function toPeerPublicArray(): array
    {
        $this->loadMissing(['region:id,name', 'constituencyRef:id,name,region_id']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'party_id' => $this->party_id,
            'occupation' => $this->occupation,
            'comms_level' => $this->comms_level,
            'region_id' => $this->region_id,
            'constituency_id' => $this->constituency_id,
            'region_name' => $this->region?->name,
            'constituency_name' => $this->constituencyRef?->name ?? $this->constituency,
        ];
    }

    /** Directory list row. */
    public function toDirectoryArray(): array
    {
        $peer = $this->toPeerPublicArray();
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $peer['first_name'] = $parts[0] !== '' ? $parts[0] : $this->name;
        $peer['tag'] = $this->comms_level === 'national'
            ? 'National'
            : ($peer['constituency_name'] ?: 'Constituency');

        return $peer;
    }
}
