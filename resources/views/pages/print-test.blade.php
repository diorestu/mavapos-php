@extends('layouts.app')

@section('content')
    <div x-data="bluetoothPrinterTest()" class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li><a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a></li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Test Printing</li>
                    </ol>
                </nav>
                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Test Printing</h1>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Uji koneksi printer thermal Bluetooth BLE dan kirim sample ESC/POS.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" @click="connect()" :disabled="isBusy"
                    class="inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-text="device ? 'Ganti Printer' : 'Hubungkan Printer'"></span>
                </button>
                <button type="button" @click="disconnect()" :disabled="!device || isBusy"
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                    Putuskan
                </button>
            </div>
        </div>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Service UUID</span>
                            <input x-model="serviceUuid" type="text" autocomplete="off"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Characteristic UUID</span>
                            <input x-model="characteristicUuid" type="text" autocomplete="off"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </label>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-[1fr_120px_120px]">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Nama Printer</span>
                            <input x-model="namePrefix" type="text" placeholder="RPP, MTP, POS"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Chunk</span>
                            <input x-model.number="chunkSize" type="number" min="20" max="512"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Delay ms</span>
                            <input x-model.number="chunkDelay" type="number" min="0" max="500"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Konten Test Print</h2>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Teks ini dikirim sebagai ESC/POS plain text dengan inisialisasi, feed, dan cut.</p>
                        </div>
                        <button type="button" @click="printSample()" :disabled="!characteristic || isBusy"
                            class="inline-flex h-9 items-center justify-center rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white shadow-theme-xs transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-gray-900">
                            Print Test
                        </button>
                    </div>

                    <textarea x-model="receiptText" rows="13"
                        class="mt-3 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 font-mono text-xs text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Status</h2>
                    <dl class="mt-3 space-y-2 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Browser</dt>
                            <dd class="font-semibold" :class="isSupported ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'" x-text="isSupported ? 'Mendukung' : 'Tidak mendukung'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Koneksi</dt>
                            <dd class="font-semibold text-gray-800 dark:text-white/90" x-text="connectionLabel"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Device</dt>
                            <dd class="max-w-[180px] truncate font-semibold text-gray-800 dark:text-white/90" x-text="deviceName"></dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Log</h2>
                    <div class="mt-3 h-[280px] overflow-y-auto rounded-lg bg-gray-950 p-3 font-mono text-[11px] leading-relaxed text-gray-100">
                        <template x-for="(entry, index) in logs" :key="index">
                            <p x-text="entry"></p>
                        </template>
                    </div>
                </div>
            </aside>
        </section>
    </div>
@endsection
