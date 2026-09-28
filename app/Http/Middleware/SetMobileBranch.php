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
        $branchId = $user?->role === 'kasir' && $user->branch_id
            ? $user->branch_id
            : (int) ($request->input('branch_id') ?: Branch::query()->where('user_id', $user->tenantOwnerId())->orderBy('id')->value('id'));
        $branch = Branch::withoutGlobalScopes()->whereKey($branchId)->where('user_id', $user->tenantOwnerId())->where('is_active', true)->firstOrFail();
        app(BranchContext::class)->setActive($branch->id);

        return $next($request);
    }
}
