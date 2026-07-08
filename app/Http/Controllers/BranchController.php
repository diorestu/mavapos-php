<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\BranchContext;
use App\Support\BranchInventoryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $ownerId = \App\Models\User::where('role', 'owner')->where('trial_ends_at', $user->trial_ends_at)->value('id') ?? $user->id;

        $branches = Branch::query()
            ->where(function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId)->orWhereNull('user_id');
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('pages.branches.index', [
            'title' => 'Cabang',
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:80', 'unique:branches,code'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        $ownerId = \App\Models\User::where('role', 'owner')->where('trial_ends_at', $user->trial_ends_at)->value('id') ?? $user->id;

        $branch = Branch::query()->create([
            'user_id' => $ownerId,
            'name' => $validated['name'],
            'code' => $validated['code'] ? Str::slug($validated['code']) : $this->uniqueCode($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        app(BranchInventoryManager::class)->initializeBranch($branch->id);

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil ditambahkan.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('branches', 'code')->ignore($branch->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! ($validated['is_active'] ?? false) && Branch::query()->where('is_active', true)->whereKeyNot($branch->id)->doesntExist()) {
            return back()->withErrors(['branch' => 'Minimal harus ada satu cabang aktif.']);
        }

        $branch->update([
            'name' => $validated['name'],
            'code' => Str::slug($validated['code']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil diperbarui.');
    }

    public function switch(Request $request, BranchContext $branchContext): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branchContext->setActive((int) $validated['branch_id']);

        return back()->with('status', 'Cabang aktif diganti.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'cabang';
        $code = $base;
        $suffix = 2;

        while (Branch::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
