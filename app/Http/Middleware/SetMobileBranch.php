<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetMobileBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ownerId = $user->tenantOwnerId();
        $branchId = $user?->role === 'kasir' && $user->branch_id
            ? $user->branch_id
            : (int) ($request->input('branch_id') ?: Branch::withoutGlobalScopes()->where('is_active', true)->where(function ($query) use ($ownerId) {
                $query->where('user_id', $ownerId);
                if (\App\Models\User::withoutGlobalScopes()->where('role', 'owner')->count() === 1) {
                    $query->orWhereNull('user_id');
                }
            })->orderBy('id')->value('id'));
        $branch = Branch::withoutGlobalScopes()->whereKey($branchId)->where('is_active', true)->where(function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
            if (\App\Models\User::withoutGlobalScopes()->where('role', 'owner')->count() === 1) {
                $query->orWhereNull('user_id');
            }
        })->firstOrFail();
        app(BranchContext::class)->setActive($branch->id);

        return $next($request);
    }
}
