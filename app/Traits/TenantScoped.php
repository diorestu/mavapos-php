<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check() && ! $model->user_id) {
                $user = auth()->user();
                $ownerId = User::where('role', 'owner')->where('trial_ends_at', $user->trial_ends_at)->value('id') ?? $user->id;
                $model->user_id = $ownerId;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                $ownerId = User::where('role', 'owner')->where('trial_ends_at', $user->trial_ends_at)->value('id') ?? $user->id;
                
                $builder->where(function ($q) use ($ownerId, $builder) {
                    $q->where($builder->getModel()->getTable() . '.user_id', $ownerId)
                      ->orWhereNull($builder->getModel()->getTable() . '.user_id');
                });
            }
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
