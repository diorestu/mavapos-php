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

        $branch = $branchId
            ? Branch::query()->whereKey($branchId)->where('is_active', true)->first()
            : null;

        if (! $branch) {
            $branch = Branch::query()->where('is_active', true)->orderBy('id')->first()
                ?? $this->createDefaultBranch();

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
        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('is_active', true)
            ->firstOrFail();

        Session::put(self::SESSION_KEY, $branch->id);

        return $branch;
    }

    public function options(): Collection
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function createDefaultBranch(): Branch
    {
        $baseCode = 'utama';
        $code = $baseCode;
        $suffix = 2;

        while (Branch::query()->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix++;
        }

        return Branch::query()->create([
            'name' => 'Cabang Utama',
            'code' => Str::slug($code),
            'is_active' => true,
        ]);
    }
}
