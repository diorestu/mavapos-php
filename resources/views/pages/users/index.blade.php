@extends('layouts.app')

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Manajemen User</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola akses owner, admin, kasir, dan gudang.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('users.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf

                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Tambah User</h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama</label>
                        <input name="name" value="{{ old('name') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input name="email" type="email" value="{{ old('email') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Role</label>
                        <select name="role" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 pr-9 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input name="password" type="password" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Konfirmasi Password</label>
                        <input name="password_confirmation" type="password" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>
                </div>

                <button type="submit" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600">
                    Tambah User
                </button>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar User</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $users->count() }} user aktif</p>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($users as $user)
                        <div class="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] lg:items-start">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</p>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">{{ $roles[$user->role] ?? $user->role }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                @if ($user->is(auth()->user()))
                                    <p class="mt-1 text-[11px] font-medium text-brand-600 dark:text-brand-400">Akun sedang digunakan</p>
                                @endif
                            </div>

                            <div class="min-w-0 space-y-2">
                                <form method="POST" action="{{ route('users.update', $user) }}" class="grid gap-2 sm:grid-cols-2">
                                    @csrf
                                    @method('PATCH')
                                    <input name="name" value="{{ $user->name }}" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                    <input name="email" type="email" value="{{ $user->email }}" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                    <select name="role" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 pr-9 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                        @foreach ($roles as $value => $label)
                                            <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input name="password" type="password" placeholder="Password baru opsional" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                    <input name="password_confirmation" type="password" placeholder="Konfirmasi password" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                                        Simpan
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('users.destroy', $user) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-error-200 px-3 text-xs font-semibold text-error-600 transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-error-500/20 dark:text-error-400 dark:hover:bg-error-500/10" @disabled($user->is(auth()->user()))>
                                        Nonaktifkan
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
