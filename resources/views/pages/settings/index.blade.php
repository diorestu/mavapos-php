@extends('layouts.app')

@php
    $businessTypes = [
        'cafe' => 'Cafe',
        'restoran' => 'Restoran',
        'retail' => 'Retail',
        'salon' => 'Salon',
        'laundry' => 'Laundry',
        'lainnya' => 'Lainnya',
    ];

    $productToggles = [
        'barcode_enabled' => 'Barcode',
        'selling_price_enabled' => 'Harga Jual',
        'cost_price_enabled' => 'Harga Modal/HPP',
        'product_status_enabled' => 'Produk Aktif/Nonaktif',
        'cashier_favorite_enabled' => 'Produk Favorit di Kasir',
        'taxable_default' => 'Produk Kena Pajak',
        'discountable_default' => 'Produk Bisa Diskon',
        'kitchen_notes_enabled' => 'Catatan Dapur',
        'dine_in_takeaway_enabled' => 'Produk Dine-in/Takeaway',
        'serving_time_enabled' => 'Estimasi Waktu Penyajian',
    ];
@endphp

@section('content')
    <div x-data="{ activeTab: 'basic' }" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <li>
                            <a href="{{ url('/') }}" class="transition hover:text-gray-700 dark:hover:text-gray-200">Home</a>
                        </li>
                        <li aria-hidden="true">
                            <svg class="h-3 w-3 stroke-current" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        <li class="font-medium text-gray-700 dark:text-gray-300">Pengaturan</li>
                    </ol>
                </nav>

                <h1 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">Pengaturan</h1>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data"
            class="grid grid-cols-1 gap-4 xl:grid-cols-[248px_minmax(0,1fr)]">
            @csrf
            @method('PATCH')

            <aside class="h-fit rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-800 dark:bg-white/[0.03]">
                <button type="button" @click="activeTab = 'basic'"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition"
                    :class="activeTab === 'basic' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-current shadow-theme-xs dark:bg-gray-900">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.16663 8.33333L9.99996 4.16666L15.8333 8.33333V15.8333H4.16663V8.33333Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M8.33337 15.8333V11.6667H11.6667V15.8333" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>
                        Pengaturan Dasar
                        <span class="block text-[11px] font-normal opacity-75">Identitas bisnis</span>
                    </span>
                </button>

                <button type="button" @click="activeTab = 'product'"
                    class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition"
                    :class="activeTab === 'product' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-current shadow-theme-xs dark:bg-gray-900">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.5 8.5L10 5.5L15.5 8.5V14.5L10 17.5L4.5 14.5V8.5Z" stroke="currentColor" stroke-width="1.5" />
                            <path d="M4.75 8.75L10 11.75L15.25 8.75M10 17V11.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span>
                        Pengaturan Produk
                        <span class="block text-[11px] font-normal opacity-75">Aturan produk global</span>
                    </span>
                </button>

                <button type="button" @click="activeTab = 'receipt'"
                    class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition"
                    :class="activeTab === 'receipt' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-current shadow-theme-xs dark:bg-gray-900">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 3.75H15V16.25L12.5 15L10 16.25L7.5 15L5 16.25V3.75Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M7.5 7H12.5M7.5 10H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span>
                        Struk & Printer
                        <span class="block text-[11px] font-normal opacity-75">Template nota cetak</span>
                    </span>
                </button>

                <button type="button" @click="activeTab = 'api'"
                    class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition"
                    :class="activeTab === 'api' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-current shadow-theme-xs dark:bg-gray-900">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                            <line x1="6" y1="6" x2="6.01" y2="6" />
                            <line x1="6" y1="18" x2="6.01" y2="18" />
                        </svg>
                    </span>
                    <span>
                        Integrasi API
                        <span class="block text-[11px] font-normal opacity-75">Kunci akses developer</span>
                    </span>
                </button>

                <a href="{{ route('billings') }}"
                    class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.04]">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-current shadow-theme-xs dark:bg-gray-900">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4.16663 5.83333C4.16663 5.3731 4.53972 5 4.99996 5H15C15.4602 5 15.8333 5.3731 15.8333 5.83333V14.1667C15.8333 14.6269 15.4602 15 15 15H4.99996C4.53972 15 4.16663 14.6269 4.16663 14.1667V5.83333Z" stroke="currentColor" stroke-width="1.4" />
                            <path d="M6.66663 8.33333H13.3333M6.66663 10.8333H10.8333" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span>
                        Billing
                        <span class="block text-[11px] font-normal opacity-75">Plan & pembayaran SaaS</span>
                    </span>
                </a>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-900/60">
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Nama Bisnis</p>
                    <p class="mt-0.5 truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $setting->store_name }}</p>
                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Mata Uang</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $setting->currency ?: 'IDR' }}</p>
                </div>

                <button type="submit"
                    class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                    Simpan
                </button>
            </aside>

            <div class="min-w-0 space-y-4">
                <section x-show="activeTab === 'basic'" x-cloak
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pengaturan Dasar</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Identitas outlet dan informasi bisnis utama.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Bisnis<span class="text-error-500">*</span></span>
                            <input name="store_name" value="{{ old('store_name', $setting->store_name) }}" required
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('store_name')
                                <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tagline</span>
                            <input name="tagline" value="{{ old('tagline', $setting->tagline) }}"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('tagline')
                                <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block lg:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Alamat Outlet</span>
                            <textarea name="address" rows="3"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('address', $setting->address) }}</textarea>
                            @error('address')
                                <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Akun Instagram</span>
                            <input name="instagram" value="{{ old('instagram', $setting->instagram) }}" placeholder="@namaakun"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('instagram')
                                <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <input type="hidden" name="phone" value="{{ old('phone', $setting->phone) }}">

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email Bisnis</span>
                            <input name="email" value="{{ old('email', $setting->email) }}"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('email')
                                <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">NPWP / NIB</span>
                            <input name="tax_number" value="{{ old('tax_number', $setting->tax_number) }}"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('tax_number')
                                <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tipe Bisnis</span>
                            <select name="business_type"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                @foreach ($businessTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('business_type', $setting->business_type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Mata Uang</span>
                            <select name="currency"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="IDR" @selected(old('currency', $setting->currency) === 'IDR')>IDR</option>
                            </select>
                        </label>

                        <label class="block lg:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Jam Operasional</span>
                            <textarea name="operational_hours" rows="3"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('operational_hours', $setting->operational_hours) }}</textarea>
                        </label>

                        <input type="hidden" name="legal_name" value="{{ old('legal_name', $setting->legal_name) }}">
                        <input type="hidden" name="owner_name" value="{{ old('owner_name', $setting->owner_name) }}">
                        <input type="hidden" name="whatsapp" value="{{ old('whatsapp', $setting->whatsapp) }}">
                        <input type="hidden" name="website" value="{{ old('website', $setting->website) }}">
                        <input type="hidden" name="facebook" value="{{ old('facebook', $setting->facebook) }}">
                        <input type="hidden" name="tiktok" value="{{ old('tiktok', $setting->tiktok) }}">
                        <input type="hidden" name="notes" value="{{ old('notes', $setting->notes) }}">
                    </div>
                </section>

                <section x-show="activeTab === 'product'" x-cloak
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pengaturan Produk</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Aturan produk global untuk modul produk dan kasir.</p>
                    </div>

                    <div class="space-y-5 p-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            @foreach ([
                                'product_categories' => ['Kategori Produk', 'Minuman, Makanan, Dessert'],
                                'product_units' => ['Satuan Produk', 'pcs, box, kg, gram, liter, porsi'],
                                'product_brands' => ['Brand Produk', 'Brand A, Brand B'],
                                'product_variants' => ['Varian Produk', 'Ukuran, Warna, Rasa'],
                                'product_modifiers' => ['Modifier/Add-on', 'Extra shot, Topping, Saus'],
                            ] as $field => [$label, $placeholder])
                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</span>
                                    <textarea name="{{ $field }}" rows="3" placeholder="{{ $placeholder }}"
                                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old($field, $setting->{$field}) }}</textarea>
                                    @error($field)
                                        <span class="mt-1 block text-xs text-error-500">{{ $message }}</span>
                                    @enderror
                                </label>
                            @endforeach

                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">SKU Otomatis/Manual</span>
                                <select name="sku_mode"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="manual" @selected(old('sku_mode', $setting->sku_mode) === 'manual')>Manual</option>
                                    <option value="auto" @selected(old('sku_mode', $setting->sku_mode) === 'auto')>Otomatis</option>
                                </select>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($productToggles as $field => $label)
                                <label class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $setting->{$field}))
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Khusus F&B</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-3">
                                @foreach ([
                                    'spicy_levels' => ['Level Pedas', 'Tidak pedas, Sedang, Pedas'],
                                    'toppings' => ['Topping', 'Keju, Boba, Telur'],
                                    'size_options' => ['Pilihan Ukuran', 'Regular, Large'],
                                ] as $field => [$label, $placeholder])
                                    <label class="block">
                                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</span>
                                        <textarea name="{{ $field }}" rows="3" placeholder="{{ $placeholder }}"
                                            class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old($field, $setting->{$field}) }}</textarea>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'receipt'" x-cloak
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Struk & Printer</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Atur tampilan struk dan preferensi cetak kasir.</p>
                    </div>

                    <div class="space-y-5 p-4">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Lebar Kertas</span>
                                <select name="receipt_paper_width"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-9 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="58" @selected(old('receipt_paper_width', $setting->receipt_paper_width) === '58')>58 mm</option>
                                    <option value="80" @selected(old('receipt_paper_width', $setting->receipt_paper_width) === '80')>80 mm</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Mode Printer</span>
                                <select name="printer_connection_mode"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-9 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <option value="browser" @selected(old('printer_connection_mode', $setting->printer_connection_mode) === 'browser')>Browser Print</option>
                                    <option value="bluetooth" @selected(old('printer_connection_mode', $setting->printer_connection_mode) === 'bluetooth')>Web Bluetooth</option>
                                    <option value="imin_inner_printer" @selected(old('printer_connection_mode', $setting->printer_connection_mode) === 'imin_inner_printer')>IMIN InnerPrinter</option>
                                </select>
                            </label>

                            <label class="block lg:col-span-2">
                                <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Catatan Footer Struk</span>
                                <textarea name="receipt_footer_note" rows="3"
                                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('receipt_footer_note', $setting->receipt_footer_note) }}</textarea>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ([
                                'receipt_show_store_address' => 'Tampilkan Alamat',
                                'receipt_show_cashier' => 'Tampilkan Kasir',
                                'printer_auto_print' => 'Auto-print Setelah Checkout',
                                'printer_close_after_print' => 'Tutup Popup Setelah Print',
                            ] as $field => $label)
                                <label class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $setting->{$field}))
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Web Bluetooth</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Service UUID</span>
                                    <input name="printer_bluetooth_service_uuid" value="{{ old('printer_bluetooth_service_uuid', $setting->printer_bluetooth_service_uuid) }}"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </label>
                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Characteristic UUID</span>
                                    <input name="printer_bluetooth_characteristic_uuid" value="{{ old('printer_bluetooth_characteristic_uuid', $setting->printer_bluetooth_characteristic_uuid) }}"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'api'" x-cloak
                    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                    x-data="{
                        tokens: [],
                        newTokenName: '',
                        createdToken: '',
                        isLoading: false,
                        fetchTokens() {
                            fetch('{{ route('settings.tokens.index') }}')
                                .then(res => res.json())
                                .then(data => this.tokens = data);
                        },
                        generateToken() {
                            if (!this.newTokenName.trim()) return;
                            this.isLoading = true;
                            fetch('{{ route('settings.tokens.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ name: this.newTokenName })
                            })
                            .then(res => res.json())
                            .then(data => {
                                this.createdToken = data.token;
                                this.newTokenName = '';
                                this.fetchTokens();
                            })
                            .finally(() => this.isLoading = false);
                        },
                        revokeToken(id) {
                            if (!confirm('Apakah Anda yakin ingin mematikan kunci API ini? Aplikasi pihak ketiga yang menggunakannya akan kehilangan akses.')) return;
                            fetch(`/settings/tokens/${id}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            })
                            .then(() => this.fetchTokens());
                        }
                    }"
                    x-init="fetchTokens()">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Integrasi API Pihak Ketiga</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Buat dan kelola token API untuk mengizinkan aplikasi eksternal terhubung secara aman dengan toko Anda.</p>
                    </div>

                    <div class="space-y-4 p-4">
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
                            <h3 class="text-xs font-semibold text-gray-800 dark:text-white/90">Buat API Key Baru</h3>
                            <div class="mt-2.5 flex flex-col gap-2 sm:flex-row">
                                <input type="text" x-model="newTokenName" placeholder="Contoh: Integrasi WooCommerce"
                                    class="h-10 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                <button type="button" @click="generateToken()" :disabled="isLoading || !newTokenName.trim()"
                                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!isLoading">Generate Key</span>
                                    <span x-show="isLoading">Loading...</span>
                                </button>
                            </div>
                        </div>

                        <template x-if="createdToken">
                            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                                <h4 class="text-xs font-bold text-success-800 dark:text-success-400">Kunci API Berhasil Dibuat!</h4>
                                <p class="mt-1 text-xs text-success-700 dark:text-success-400/90">
                                    Salin kunci API di bawah ini sekarang. Demi keamanan, Anda tidak akan bisa melihat kunci ini lagi setelah meninggalkan halaman ini.
                                </p>
                                <div class="mt-2.5 flex items-center gap-2">
                                    <input type="text" readonly :value="createdToken"
                                        class="h-10 flex-1 rounded-lg border border-success-300 bg-white px-3 font-mono text-xs text-gray-800 dark:border-success-800/40 dark:bg-gray-950 dark:text-white/95" />
                                    <button type="button" @click="navigator.clipboard.writeText(createdToken); alert('API Key disalin ke clipboard.')"
                                        class="inline-flex h-10 items-center justify-center rounded-lg bg-success-600 px-3 text-xs font-semibold text-white hover:bg-success-700">
                                        Salin
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                            <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
                                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">Nama API Key</th>
                                        <th class="px-4 py-3 font-semibold">Dibuat</th>
                                        <th class="px-4 py-3 font-semibold">Penggunaan Terakhir</th>
                                        <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <template x-for="token in tokens" :key="token.id">
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.01]">
                                            <td class="px-4 py-3 font-semibold text-gray-800 dark:text-white/90" x-text="token.name"></td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400" x-text="token.created_at"></td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400" x-text="token.last_used_at"></td>
                                            <td class="px-4 py-3 text-right">
                                                <button type="button" @click="revokeToken(token.id)"
                                                    class="text-error-600 hover:text-error-700 font-semibold">
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="tokens.length === 0">
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                                Belum ada kunci API aktif.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </form>
    </div>
@endsection
