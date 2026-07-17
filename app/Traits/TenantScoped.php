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
                $model->user_id = $user->tenantOwnerId();
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                $ownerId = $user->tenantOwnerId();
                
                $builder->where(function ($q) use ($ownerId, $builder) {
                    $q->where($builder->getModel()->getTable() . '.user_id', $ownerId);
                });
            }
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
