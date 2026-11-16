<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartnerApiKey extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['sandbox' => 'boolean', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function usable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture() && $this->owner?->role === 'owner';
    }

    public static function issue(User $owner, string $name, bool $sandbox, DateTimeInterface $expiresAt): array
    {
        $plain = ($sandbox ? 'mava_test_' : 'mava_live_').Str::random(64);
        $key = static::create(['owner_id' => $owner->tenantOwnerId(), 'name' => $name, 'token_hash' => hash('sha256', $plain), 'prefix' => substr($plain, 0, 18), 'sandbox' => $sandbox, 'expires_at' => $expiresAt]);

        return [$key, $plain];
    }
}
