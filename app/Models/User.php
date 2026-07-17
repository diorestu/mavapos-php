<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'google_id',
        'role',
        'trial_ends_at',
        'tenant_owner_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'tenant_owner_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->tenant_owner_id === null && $user->role === 'owner') {
                $user->forceFill(['tenant_owner_id' => $user->id])->saveQuietly();
            }
        });
    }

    public function tenantOwnerId(): int
    {
        if ($this->tenant_owner_id) {
            return (int) $this->tenant_owner_id;
        }

        if ($this->role === 'owner') {
            return (int) $this->id;
        }

        $legacyOwnerId = static::query()->where('role', 'owner')->where('trial_ends_at', $this->trial_ends_at)->value('id');
        if ($legacyOwnerId) {
            return (int) $legacyOwnerId;
        }

        $ownerIds = static::query()->where('role', 'owner')->pluck('id');

        return $ownerIds->count() === 1 ? (int) $ownerIds->first() : (int) $this->id;
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return in_array($this->role, $roles, true);
    }

    public function isTrialActive(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }

    public function cashierShifts(): HasMany
    {
        return $this->hasMany(CashierShift::class);
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }
}
