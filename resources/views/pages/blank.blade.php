@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title ?? 'Halaman'" />
    <div class="min-h-screen rounded-2xl border border-gray-200 bg-white px-5 py-7 dark:border-gray-800 dark:bg-white/[0.03] xl:px-10 xl:py-12">
        <div class="mx-auto w-full max-w-[630px] text-center">
            <h3 class="mb-4 font-semibold text-gray-800 text-theme-xl dark:text-white/90 sm:text-2xl">
                {{ $title ?? 'Halaman' }}
            </h3>

            <p class="text-sm text-gray-500 dark:text-gray-400 sm:text-base">
                Modul ini sudah tersedia di menu POS dan siap dikembangkan pada tahap berikutnya.
            </p>
        </div>
    </div>
@endsection
