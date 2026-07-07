@extends('layouts.app')

@section('content')
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Cabang</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola outlet/cabang dan tentukan cabang aktif dari header.</p>
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
            <form method="POST" action="{{ route('branches.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                @csrf

                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Tambah Cabang</h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama Cabang</label>
                        <input name="name" value="{{ old('name') }}" placeholder="Cabang Makassar" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kode</label>
                        <input name="code" value="{{ old('code') }}" placeholder="makassar" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Opsional. Jika kosong, kode dibuat dari nama cabang.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Telepon</label>
                        <input name="phone" value="{{ old('phone') }}" class="h-10 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Alamat</label>
                        <textarea name="address" rows="3" class="w-full rounded-lg border border-gray-200 bg-transparent px-3 py-2 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">{{ old('address') }}</textarea>
                    </div>
                </div>

                <button type="submit" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600">
                    Tambah Cabang
                </button>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar Cabang</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $branches->count() }} cabang terdaftar</p>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($branches as $branch)
                        <div class="grid gap-3 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)] lg:items-start">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $branch->name }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $branch->is_active ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400' }}">
                                        {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $branch->code }}</p>
                                @if ($branch->address)
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $branch->address }}</p>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('branches.update', $branch) }}" class="grid gap-2 sm:grid-cols-2">
                                @csrf
                                @method('PATCH')
                                <input name="name" value="{{ $branch->name }}" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                <input name="code" value="{{ $branch->code }}" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                <input name="phone" value="{{ $branch->phone }}" placeholder="Telepon" class="h-9 rounded-lg border border-gray-200 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">
                                <label class="flex h-9 items-center gap-2 rounded-lg border border-gray-200 px-3 text-xs text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($branch->is_active) class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20">
                                    Aktif
                                </label>
                                <textarea name="address" rows="2" class="sm:col-span-2 rounded-lg border border-gray-200 bg-transparent px-3 py-2 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white/90">{{ $branch->address }}</textarea>
                                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                                    Simpan
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
