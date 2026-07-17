<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class BranchContext
{
    private const SESSION_KEY = 'active_branch_id';

    public function active(): Branch
    {
        $branchId = Session::get(self::SESSION_KEY);
        $user = auth()->user();
        $ownerId = $user?->tenantOwnerId();

        $branch = $branchId
            ? Branch::withoutGlobalScope('tenant')->whereKey($branchId)->where('is_active', true)->first()
            : null;

        if ($branch && $ownerId && $branch->user_id !== null && $branch->user_id !== $ownerId) {
            $branch = null;
        }

        if (! $branch) {
            $query = Branch::withoutGlobalScope('tenant')->where('is_active', true);
            if ($ownerId) {
                $query->where(function ($q) use ($ownerId) {
                    $q->where('user_id', $ownerId);
                    if (\App\Models\User::query()->where('role', 'owner')->count() === 1) {
                        $q->orWhereNull('user_id');
                    }
                });
            }
            $branch = $query->orderBy('id')->first()
                ?? $this->createDefaultBranch($ownerId);

            Session::put(self::SESSION_KEY, $branch->id);
        }

        return $branch;
    }

    public function activeId(): int
    {
        return $this->active()->id;
    }

    public function setActive(int $branchId): Branch
    {
        $user = auth()->user();
        $ownerId = $user?->tenantOwnerId();

        $query = Branch::withoutGlobalScope('tenant')->whereKey($branchId)->where('is_active', true);
        if ($ownerId) {
            $query->where(function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
                if (\App\Models\User::query()->where('role', 'owner')->count() === 1) {
                    $q->orWhereNull('user_id');
                }
            });
        }

        $branch = $query->firstOrFail();

        Session::put(self::SESSION_KEY, $branch->id);

        return $branch;
    }

    public function options(): Collection
    {
        $user = auth()->user();
        $ownerId = $user?->tenantOwnerId();

        $query = Branch::withoutGlobalScope('tenant')->where('is_active', true);
        if ($ownerId) {
            $query->where(function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId);
            });
        }

        return $query->orderBy('name')->get();
    }

    private function createDefaultBranch(?int $ownerId = null): Branch
    {
        $baseCode = 'utama';
        $code = $baseCode;
        $suffix = 2;
        while (Branch::withoutGlobalScopes()->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix++;
        }

        return Branch::query()->create([
            'user_id' => $ownerId,
            'name' => 'Cabang Utama',
            'code' => Str::slug($code),
            'is_active' => true,
        ]);
    }
}
