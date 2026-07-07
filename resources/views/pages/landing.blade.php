@extends('layouts.fullscreen-layout')

@section('content')
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --mava-brand-50: #ecf3ff;
        --mava-brand-100: #dde9ff;
        --mava-brand-500: #465fff;
        --mava-brand-600: #3641f5;
        --mava-brand-700: #2a31d8;
        --mava-brand-900: #1a2238;
        --mava-dark: #101828;
        --mava-gray-50: #f9fafb;
        --mava-gray-100: #f2f4f7;
        --mava-gray-500: #667085;
        --mava-gray-700: #344054;
        --mava-ease: cubic-bezier(0.4, 0, 0.2, 1);
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: "Plus Jakarta Sans", sans-serif !important;
        background-color: var(--mava-gray-50);
        color: var(--mava-dark);
    }

    /* Gradient Backgrounds */
    .hero-glow-1 {
        position: absolute;
        top: -10%;
        left: -10%;
        width: 50%;
        height: 60%;
        background: radial-gradient(circle, rgba(70, 95, 255, 0.12) 0%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    .hero-glow-2 {
        position: absolute;
        bottom: 10%;
        right: -10%;
        width: 50%;
        height: 60%;
        background: radial-gradient(circle, rgba(0, 134, 201, 0.08) 0%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    /* Header scroll shadow transition */
    .header-blur {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(228, 231, 236, 0.8);
        transition: padding 300ms var(--mava-ease), box-shadow 300ms var(--mava-ease);
    }

    .header-blur.is-scrolled {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
        box-shadow: 0 10px 30px -10px rgba(16, 24, 40, 0.08);
        background: rgba(255, 255, 255, 0.9);
    }

    /* Premium card styles */
    .premium-card {
        background: #ffffff;
        border: 1px solid #e4e7ec;
        border-radius: 20px;
        transition: transform 300ms var(--mava-ease), box-shadow 300ms var(--mava-ease), border-color 300ms var(--mava-ease);
    }

    .premium-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(16, 24, 40, 0.05), 0 10px 10px -5px rgba(16, 24, 40, 0.02);
        border-color: rgba(70, 95, 255, 0.25);
    }

    /* Interactive tab active styles */
    .tab-btn {
        transition: all 200ms var(--mava-ease);
    }

    .tab-btn.active {
        background-color: var(--mava-brand-50);
        color: var(--mava-brand-600);
        border-color: var(--mava-brand-500);
    }

    /* Custom scrollbar for horizontal brand slider */
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    /* FAQ smooth details */
    details summary::-webkit-details-marker {
        display: none;
    }

    details summary {
        list-style: none;
    }

    details[open] summary svg {
        transform: rotate(180deg);
    }
</style>

<div class="relative min-h-screen overflow-x-hidden bg-gray-50/50">
    <!-- Header -->
    <header id="landing-header" class="header-blur fixed left-0 right-0 top-0 z-9999 px-4 py-5">
        <div class="mx-auto flex max-w-7xl items-center justify-between">
            <!-- Logo -->
            <a href="#" class="flex items-center gap-3 rounded-xl focus:outline-none">
                <img src="{{ asset('logo.png') }}" class="h-8 w-auto" alt="MavaPOS Logo">
            </a>

            <!-- Navigation Links -->
            <nav class="hidden items-center gap-1 rounded-full bg-gray-100/80 p-1 text-sm font-medium md:flex" aria-label="Navigasi Utama">
                <a href="#fitur" class="rounded-full px-4 py-2 text-gray-600 transition hover:bg-white hover:text-brand-500">Fitur</a>
                <a href="#alur" class="rounded-full px-4 py-2 text-gray-600 transition hover:bg-white hover:text-brand-500">Alur Kerja</a>
                <a href="#jenis-usaha" class="rounded-full px-4 py-2 text-gray-600 transition hover:bg-white hover:text-brand-500">Jenis Usaha</a>
                <a href="#harga" class="rounded-full px-4 py-2 text-gray-600 transition hover:bg-white hover:text-brand-500">Harga</a>
                <a href="#faq" class="rounded-full px-4 py-2 text-gray-600 transition hover:bg-white hover:text-brand-500">FAQ</a>
            </nav>

            <!-- Buttons -->
            <div class="flex items-center gap-3">
                <a href="{{ route('signin') }}" class="rounded-full px-4 py-2 text-sm font-bold text-gray-700 transition hover:text-brand-600">Masuk</a>
                <a href="{{ route('signup') }}" class="rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white shadow-theme-md transition hover:bg-brand-600">Daftar Gratis</a>
            </div>
        </div>
    </header>

    <main class="pt-24">
        <!-- Hero Section -->
        <section class="relative px-4 pb-20 pt-16 lg:pt-24">
            <div class="hero-glow-1"></div>
            <div class="hero-glow-2"></div>
            
            <div class="mx-auto max-w-7xl">
                <div class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
                    <!-- Copywriting -->
                    <div class="space-y-8 text-center lg:text-left">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-4 py-1.5 text-xs font-bold tracking-wide text-brand-600">
                            🚀 SISTEM APLIKASI KASIR CLOUD UNTUK UMKM MODERN
                        </span>
                        
                        <div class="space-y-5">
                            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl lg:leading-[1.15]">
                                Satu Aplikasi Kasir untuk <span class="text-brand-500">Semua Kebutuhan</span> Bisnis Anda
                            </h1>
                            <p class="mx-auto max-w-2xl text-lg leading-relaxed text-gray-500 lg:mx-0">
                                MavaPOS mengintegrasikan pencatatan kasir, resep bahan baku, manajemen stok otomatis, database pelanggan, dan laporan keuangan komprehensif dalam satu ruang kerja berbasis online.
                            </p>
                        </div>

                        <!-- CTA Actions -->
                        <div class="flex flex-col justify-center gap-4 sm:flex-row lg:justify-start">
                            <a href="{{ route('signup') }}" class="rounded-full bg-brand-500 px-8 py-4 text-center font-bold text-white shadow-theme-lg transition hover:bg-brand-600 hover:scale-[1.02]">
                                Mulai Coba Gratis 14 Hari
                            </a>
                            <a href="https://api.whatsapp.com/send?phone=6281110697700&text=Halo%20MavaPOS!%20Saya%20tertarik%20ingin%20jadwalkan%20demo%20dan%20mengetahui%20layanan%20kasir%20online%20lebih%20lanjut." target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-300 bg-white px-8 py-4 text-center font-bold text-gray-700 shadow-theme-sm transition hover:bg-gray-50">
                                <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.457L0 24zm6.59-4.846c1.6.95 3.182 1.449 4.825 1.451 5.436 0 9.86-4.37 9.864-9.799.002-2.63-1.023-5.101-2.885-6.966a9.9 9.9 0 00-6.98-2.879c-5.443 0-9.866 4.372-9.87 9.802 0 1.714.47 3.387 1.357 4.881l-.997 3.641 3.791-.989zM17.15 14.39c-.3-.15-1.782-.88-2.053-.98-.271-.1-.47-.15-.667.15-.197.3-.763.96-.935 1.16-.172.2-.345.23-.645.08-1.706-.85-2.812-1.56-3.882-3.41-.284-.49.284-.45.815-1.51.09-.18.045-.34-.02-.49-.067-.15-.667-1.61-.913-2.2-.24-.58-.485-.5-.667-.51l-.57-.01c-.197 0-.516.07-.787.37-.27.3-1.03 1.01-1.03 2.47 0 1.46 1.062 2.87 1.21 3.07.147.2 2.09 3.2 5.06 4.49.707.307 1.26.49 1.69.63.71.226 1.358.194 1.87.118.571-.085 1.782-.73 2.03-1.43.248-.7.248-1.3.172-1.43-.075-.12-.27-.2-.57-.35z"/>
                                </svg>
                                WhatsApp Sekarang
                            </a>
                        </div>
                    </div>

                    <!-- Hero Visual -->
                    <div class="relative mx-auto w-full max-w-2xl lg:max-w-none">
                        <img
                            src="{{ asset('images/brand/hero-macbook-dashboard.webp') }}"
                            class="w-full object-contain"
                            alt="Dashboard MavaPOS pada MacBook Air"
                            loading="eager"
                            fetchpriority="high"
                        >
                    </div>
                </div>
            </div>
        </section>

        <!-- Social Proof / Partners -->
        <section class="border-y border-gray-200 bg-gray-50 py-10">
            <div class="mx-auto max-w-7xl px-4 text-center">
                <p class="text-sm font-bold uppercase tracking-wider text-gray-400">
                    Dipercaya oleh 15.000+ Pebisnis & UMKM Bertumbuh di Indonesia
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-12 gap-y-6 opacity-65">
                    @foreach (range(1, 8) as $i)
                        <img src="{{ asset(sprintf('images/brand/brand-%02d.svg', $i)) }}" class="h-8 w-auto grayscale transition hover:grayscale-0" alt="Partner Logo {{ $i }}">
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Backoffice Benefits ("Pantau Usaha dengan Sat-set!") -->
        <section class="px-4 py-20 lg:py-28">
            <div class="mx-auto max-w-7xl">
                <!-- Section Title -->
                <div class="mx-auto max-w-3xl text-center space-y-4">
                    <span class="inline-flex rounded-full bg-brand-50 px-3.5 py-1 text-xs font-bold text-brand-600">SOLUSI BACKOFFICE CLOUD</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Pantau Usaha & Kelola Transaksi dengan Sat-set!
                    </h2>
                    <p class="text-lg text-gray-500">
                        Hilangkan rekap data manual yang memakan waktu. Backoffice cloud MavaPOS menyajikan analisis penjualan secara real-time di mana saja Anda berada.
                    </p>
                </div>

                <!-- Benefits Grid -->
                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <div class="premium-card p-8 space-y-6">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-500">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </span>
                        <h3 class="text-xl font-bold text-gray-900">Kelola Pesanan Terintegrasi</h3>
                        <p class="text-sm leading-relaxed text-gray-500">
                            Melayani pesanan dine-in, takeaway, delivery, hingga contactless order (pesan mandiri lewat QR) langsung dalam satu terminal kasir.
                        </p>
                    </div>

                    <div class="premium-card p-8 space-y-6">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-500">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0v8H5v-8m14 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v2" />
                            </svg>
                        </span>
                        <h3 class="text-xl font-bold text-gray-900">Stok Bahan & Resep Otomatis</h3>
                        <p class="text-sm leading-relaxed text-gray-500">
                            Setiap produk terjual akan otomatis memotong stok bahan baku berdasarkan resep produk. Bebas selisih stok saat jam sibuk operasional.
                        </p>
                    </div>

                    <div class="premium-card p-8 space-y-6">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-500">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5v8m4 0V9a2 2 0 012-2h2v12m4 0V5a2 2 0 012-2h2v16" />
                            </svg>
                        </span>
                        <h3 class="text-xl font-bold text-gray-900">Laporan Penjualan Real-time</h3>
                        <p class="text-sm leading-relaxed text-gray-500">
                            Analisis jam sibuk, performa omset, menu terlaris, dan laba kotor harian otomatis terhitung. Data siap diunduh dalam format PDF.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Core Features Grid -->
        <section id="fitur" class="bg-gray-100/40 px-4 py-20 lg:py-28">
            <div class="mx-auto max-w-7xl">
                <!-- Title -->
                <div class="mx-auto max-w-3xl text-center space-y-4">
                    <span class="inline-flex rounded-full bg-brand-50 px-3.5 py-1 text-xs font-bold text-brand-600">FITUR LENGKAP</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Satu Sistem POS untuk Menyederhanakan Alur Kerja
                    </h2>
                    <p class="text-lg text-gray-500">
                        Segala fitur penting untuk menunjang pertumbuhan bisnis UMKM offline maupun online Anda.
                    </p>
                </div>

                <!-- Features Grid -->
                <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $coreFeatures = [
                            [
                                'title' => 'Aplikasi POS Kasir Cepat',
                                'desc' => 'Antarmuka kasir responsif untuk transaksi di tablet atau komputer. Dukung multi-metode pembayaran (E-wallet, QRIS, Tunai, Kartu).',
                                'icon' => 'M9 7h6m-6 4h6m-6 4h3'
                            ],
                            [
                                'title' => 'Manajemen Bahan Baku (Resep)',
                                'desc' => 'Dukung F&B dengan link bahan baku ke menu masakan. Setiap pesanan selesai akan memotong inventaris bahan dapur secara langsung.',
                                'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'
                            ],
                            [
                                'title' => 'Multi-Outlet & Stok Kontrol',
                                'desc' => 'Pantau stok, transfer stok antar toko, stok opname berkala, dan terima alert notifikasi jika ada barang yang menipis.',
                                'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10'
                            ],
                            [
                                'title' => 'Laporan Keuangan & PDF',
                                'desc' => 'Rekap harian, laporan laba rugi bulanan, dan laporan mutasi kasir yang siap diunduh menjadi PDF atau diekspor untuk akuntansi.',
                                'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
                            ],
                            [
                                'title' => 'CRM Pelanggan & Loyalty',
                                'desc' => 'Simpan data pelanggan, pantau histori belanja mereka, dan buat promo diskon terarah untuk mendorong repeat order.',
                                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z'
                            ],
                            [
                                'title' => 'Otoritas & Hak Akses Karyawan',
                                'desc' => 'Atur hak akses terpisah untuk kasir, supervisor toko, dan admin backoffice demi menjaga keamanan data sensitif keuangan.',
                                'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
                            ],
                        ];
                    @endphp

                    @foreach ($coreFeatures as $feature)
                        <article class="premium-card p-6.5">
                            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500 text-white shadow-theme-md">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature['icon'] }}" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">{{ $feature['title'] }}</h3>
                            <p class="mt-2.5 text-sm leading-relaxed text-gray-500">{{ $feature['desc'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- "Melayani Semua Jenis Usaha" (Interactive Tabbed Section) -->
        <section id="jenis-usaha" class="px-4 py-20 lg:py-28" x-data="{ activeTab: 'fnb' }">
            <div class="mx-auto max-w-7xl">
                <!-- Title -->
                <div class="mx-auto max-w-3xl text-center space-y-4">
                    <span class="inline-flex rounded-full bg-brand-50 px-3.5 py-1 text-xs font-bold text-brand-600">FLEKSIBEL UNTUK SEMUA</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Didesain Khusus untuk Karakter Unik Usaha Anda
                    </h2>
                    <p class="text-lg text-gray-500">
                        MavaPOS menyediakan fitur khusus yang disesuaikan dengan alur operasional berbagai jenis bidang usaha.
                    </p>
                </div>

                <!-- Tabs Navigation -->
                <div class="mt-12 flex flex-wrap justify-center gap-3">
                    <button @click="activeTab = 'fnb'" :class="{'active': activeTab === 'fnb'}" class="tab-btn inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-6 py-3 text-sm font-bold text-gray-600 hover:bg-gray-50">
                        ☕ Restoran & Cafe
                    </button>
                    <button @click="activeTab = 'retail'" :class="{'active': activeTab === 'retail'}" class="tab-btn inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-6 py-3 text-sm font-bold text-gray-600 hover:bg-gray-50">
                        🛍️ Toko Retail & Butik
                    </button>
                    <button @click="activeTab = 'service'" :class="{'active': activeTab === 'service'}" class="tab-btn inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-6 py-3 text-sm font-bold text-gray-600 hover:bg-gray-50">
                        ✂️ Salon & Barbershop
                    </button>
                    <button @click="activeTab = 'franchise'" :class="{'active': activeTab === 'franchise'}" class="tab-btn inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-6 py-3 text-sm font-bold text-gray-600 hover:bg-gray-50">
                        🏢 Multi-Outlet & Waralaba
                    </button>
                </div>

                <!-- Tabs Content Panels -->
                <div class="mt-12 premium-card overflow-hidden">
                    <div class="grid lg:grid-cols-[1.1fr_0.9fr] items-center">
                        
                        <!-- Panel Info -->
                        <div class="p-8 sm:p-12 space-y-6">
                            <!-- FNB -->
                            <div x-show="activeTab === 'fnb'" class="space-y-6">
                                <h3 class="text-2xl font-bold text-gray-900">Percepat Pelayanan Makanan & Minuman</h3>
                                <p class="text-sm leading-relaxed text-gray-500">
                                    Dari sistem layout meja restoran, pencatatan resep bahan baku otomatis, hingga cetak struk pesanan ke dapur (kitchen printer). Semuanya berjalan selaras secara otomatis.
                                </p>
                                <ul class="space-y-3 text-sm text-gray-700">
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Manajemen Tata Letak Meja Restoran</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Pengaturan Menu Kombo & Modifiers (Varian)</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Kirim Struk Instan ke Layar Dapur</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Pembayaran Pisah Meja (Split Bill)</li>
                                </ul>
                            </div>

                            <!-- RETAIL -->
                            <div x-show="activeTab === 'retail'" class="space-y-6" style="display: none;">
                                <h3 class="text-2xl font-bold text-gray-900">Manajemen Stok Ribuan Item Lebih Praktis</h3>
                                <p class="text-sm leading-relaxed text-gray-500">
                                    Cocok untuk toko kelontong, minimarket, butik pakaian, dan toko hobi. Kelola ribuan SKU barang, barcode scanner cepat, serta pantau level minimum stok barang untuk re-order ke supplier.
                                </p>
                                <ul class="space-y-3 text-sm text-gray-700">
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Input SKU & Barcode Scanner Responsif</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Manajemen Diskon Multi-Item & Cuci Gudang</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Pembelian Supplier & Mutasi Barang Masuk</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Peringatan Dini Stok Barang Menipis</li>
                                </ul>
                            </div>

                            <!-- SERVICE -->
                            <div x-show="activeTab === 'service'" class="space-y-6" style="display: none;">
                                <h3 class="text-2xl font-bold text-gray-900">Atur Antrean Jasa & Performa Staff</h3>
                                <p class="text-sm leading-relaxed text-gray-500">
                                    Sangat pas untuk barbershop, salon kecantikan, spa, dan studio foto. Kelola pemesanan jadwal layanan, database penata gaya/staff, serta perhitungan komisi otomatis per transaksi layanan.
                                </p>
                                <ul class="space-y-3 text-sm text-gray-700">
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Booking Jadwal Layanan & Antrean</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Hitung Komisi Karyawan Per Transaksi</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Database Profil Riwayat Perawatan Pelanggan</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Voucher & Paket Bundling Promo Layanan</li>
                                </ul>
                            </div>

                            <!-- FRANCHISE -->
                            <div x-show="activeTab === 'franchise'" class="space-y-6" style="display: none;">
                                <h3 class="text-2xl font-bold text-gray-900">Kontrol Rantai Bisnis Waralaba Dari Satu Akun</h3>
                                <p class="text-sm leading-relaxed text-gray-500">
                                    Punya banyak outlet atau kemitraan franchise? Hubungkan seluruh cabang Anda dalam satu Backoffice dashboard. Bandingkan performa omset dan stok antar cabang tanpa ribet.
                                </p>
                                <ul class="space-y-3 text-sm text-gray-700">
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Laporan Konsolidasi Multi-Cabang</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Transfer Stok Aman Antar Gudang/Outlet</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Kontrol Harga Jual Terpusat (Centralized Price)</li>
                                    <li class="flex items-center gap-3"><span class="font-bold text-brand-500">✓</span> Hak Otoritas Multi-Manager Cabang</li>
                                </ul>
                            </div>

                            <div>
                                <a href="{{ route('signup') }}" class="inline-flex rounded-full bg-brand-500 px-6 py-3 font-bold text-white shadow-theme-md transition hover:bg-brand-600">
                                    Coba Gratis Sekarang
                                </a>
                            </div>
                        </div>

                        <!-- Panel Graphic Mockup -->
                        <div class="bg-gray-50 p-8 flex justify-center items-center h-full border-t lg:border-t-0 lg:border-l border-gray-200">
                            <!-- FNB Preview -->
                            <div x-show="activeTab === 'fnb'" class="w-full max-w-sm space-y-4">
                                <div class="rounded-2xl bg-white p-5 border border-gray-200 shadow-theme-sm">
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                        <h4 class="font-bold text-gray-800">☕ Kopi Susu Aren</h4>
                                        <span class="text-brand-500 font-bold">Resep Aktif</span>
                                    </div>
                                    <div class="mt-3 space-y-2 text-xs text-gray-500">
                                        <div class="flex justify-between">
                                            <span>🥛 Susu UHT (Bahan)</span>
                                            <span class="font-bold text-red-500">- 150ml</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>🫘 Espresso Shot (Bahan)</span>
                                            <span class="font-bold text-red-500">- 30ml</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>🍯 Gula Aren Cair (Bahan)</span>
                                            <span class="font-bold text-red-500">- 20ml</span>
                                        </div>
                                    </div>
                                    <div class="mt-4 rounded-xl bg-green-50 p-3 text-center text-xs font-bold text-green-700">
                                        Stok Bahan Otomatis Terpotong Saat Terjual
                                    </div>
                                </div>
                            </div>

                            <!-- Retail Preview -->
                            <div x-show="activeTab === 'retail'" class="w-full max-w-sm space-y-4" style="display: none;">
                                <div class="rounded-2xl bg-white p-5 border border-gray-200 shadow-theme-sm">
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                        <h4 class="font-bold text-gray-800">🏷️ SKU: CLOTH-TSHIRT-L</h4>
                                        <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded text-[10px]">Stok Tipis</span>
                                    </div>
                                    <div class="mt-3 space-y-2 text-xs text-gray-500">
                                        <div class="flex justify-between">
                                            <span>Sisa Stok Fisik</span>
                                            <span class="font-bold text-gray-900">4 Pcs</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Batas Minimum Stok</span>
                                            <span class="font-bold text-gray-900">10 Pcs</span>
                                        </div>
                                    </div>
                                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-center text-xs font-bold text-amber-700">
                                        ⚠️ Kirim Permintaan PO ke Supplier Otomatis
                                    </div>
                                </div>
                            </div>

                            <!-- Service Preview -->
                            <div x-show="activeTab === 'service'" class="w-full max-w-sm space-y-4" style="display: none;">
                                <div class="rounded-2xl bg-white p-5 border border-gray-200 shadow-theme-sm">
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                        <h4 class="font-bold text-gray-800">💈 Haircut + Wash</h4>
                                        <span class="text-brand-500 font-bold">Antrean 02</span>
                                    </div>
                                    <div class="mt-3 space-y-2 text-xs text-gray-500">
                                        <div class="flex justify-between">
                                            <span>Pelanggan</span>
                                            <span class="font-bold text-gray-950">Ahmad Budi</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Penata Gaya (Capster)</span>
                                            <span class="font-bold text-gray-950">Randi (Staff 01)</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Estimasi Komisi Staff</span>
                                            <span class="font-bold text-green-600">Rp 15.000 (30%)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Franchise Preview -->
                            <div x-show="activeTab === 'franchise'" class="w-full max-w-sm space-y-4" style="display: none;">
                                <div class="rounded-2xl bg-white p-5 border border-gray-200 shadow-theme-sm">
                                    <h4 class="font-bold text-gray-800 pb-3 border-b border-gray-100">🏢 Konsolidasi Cabang</h4>
                                    <div class="mt-3 space-y-3 text-xs text-gray-500">
                                        <div class="flex justify-between items-center">
                                            <span>📍 Outlet Senopati</span>
                                            <span class="font-bold text-gray-900">Rp 42.500.000</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span>📍 Outlet Kemang</span>
                                            <span class="font-bold text-gray-900">Rp 38.100.000</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span>📍 Outlet BSD City</span>
                                            <span class="font-bold text-gray-900">Rp 29.800.000</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistik & Kredibilitas -->
        <section class="relative bg-brand-900 px-4 py-20 text-white overflow-hidden">
            <!-- Glowing Orbs -->
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-brand-500/20 blur-3xl"></div>
            <div class="absolute -bottom-20 -left-20 h-72 w-72 rounded-full bg-blue-500/15 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl relative z-1">
                <div class="mx-auto max-w-3xl text-center space-y-4">
                    <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                        Investasi Terbaik untuk Lompatan Bisnis Anda
                    </h2>
                    <p class="text-lg text-gray-300">
                        Skala transaksi kami membuktikan keandalan sistem cloud POS MavaPOS untuk mengawal bisnis Anda.
                    </p>
                </div>

                <div class="mt-16 grid grid-cols-2 gap-8 md:grid-cols-4 text-center">
                    <div class="space-y-2">
                        <p class="text-4xl sm:text-5xl font-extrabold text-brand-100">5+ Tahun</p>
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-bold">Menemani Usaha UMKM</p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-4xl sm:text-5xl font-extrabold text-brand-100">100+</p>
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-bold">Kota di Indonesia</p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-4xl sm:text-5xl font-extrabold text-brand-100">15K+</p>
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-bold">Pebisnis Aktif</p>
                    </div>
                    <div class="space-y-2">
                        <p class="text-4xl sm:text-5xl font-extrabold text-brand-100">Rp 5.2T+</p>
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-bold">Transaksi Sukses Terproses</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Merchant Testimonials (Smooth Quotes Carousel) -->
        <section class="px-4 py-20 lg:py-28" x-data="{ activeTestimonial: 0, testimonialsCount: 3 }">
            <div class="mx-auto max-w-7xl">
                <!-- Title -->
                <div class="mx-auto max-w-3xl text-center space-y-4">
                    <span class="inline-flex rounded-full bg-brand-50 px-3.5 py-1 text-xs font-bold text-brand-600">TESTIMONI MERCHANT</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Dipercaya oleh Brand yang Sukses Bertumbuh
                    </h2>
                </div>

                <!-- Testimonial Box -->
                <div class="mt-16 mx-auto max-w-4xl premium-card overflow-hidden">
                    <div class="grid md:grid-cols-[0.8fr_1.2fr]">
                        
                        <!-- Side Photo Area -->
                        <div class="bg-gray-100 relative min-h-[300px] md:min-h-0 flex items-center justify-center">
                            <!-- Testimonial Photo 1 -->
                            <div x-show="activeTestimonial === 0" class="absolute inset-0 flex items-center justify-center p-6 bg-gradient-to-tr from-brand-600 to-indigo-900 text-white">
                                <div class="text-center space-y-4">
                                    <div class="mx-auto h-24 w-24 rounded-full border-4 border-white/20 bg-white/10 flex items-center justify-center text-3xl font-bold">JV</div>
                                    <div>
                                        <h4 class="font-bold text-lg">Jennike Veronica</h4>
                                        <p class="text-xs text-brand-100">Owner of Vilo Gelato</p>
                                    </div>
                                    <span class="inline-block bg-white/15 px-3 py-1 rounded-full text-[10px] font-bold">27 Outlet Aktif</span>
                                </div>
                            </div>

                            <!-- Testimonial Photo 2 -->
                            <div x-show="activeTestimonial === 1" class="absolute inset-0 flex items-center justify-center p-6 bg-gradient-to-tr from-emerald-600 to-teal-900 text-white" style="display: none;">
                                <div class="text-center space-y-4">
                                    <div class="mx-auto h-24 w-24 rounded-full border-4 border-white/20 bg-white/10 flex items-center justify-center text-3xl font-bold">WY</div>
                                    <div>
                                        <h4 class="font-bold text-lg">Widhi Yustiarto</h4>
                                        <p class="text-xs text-emerald-100">Owner of Double U Steak</p>
                                    </div>
                                    <span class="inline-block bg-white/15 px-3 py-1 rounded-full text-[10px] font-bold">F&B Steakhouse</span>
                                </div>
                            </div>

                            <!-- Testimonial Photo 3 -->
                            <div x-show="activeTestimonial === 2" class="absolute inset-0 flex items-center justify-center p-6 bg-gradient-to-tr from-rose-600 to-amber-900 text-white" style="display: none;">
                                <div class="text-center space-y-4">
                                    <div class="mx-auto h-24 w-24 rounded-full border-4 border-white/20 bg-white/10 flex items-center justify-center text-3xl font-bold">HI</div>
                                    <div>
                                        <h4 class="font-bold text-lg">Hadi Ismanto</h4>
                                        <p class="text-xs text-rose-100">Owner of Newsroom</p>
                                    </div>
                                    <span class="inline-block bg-white/15 px-3 py-1 rounded-full text-[10px] font-bold">Retail & Cafe</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quote Area -->
                        <div class="p-8 sm:p-12 flex flex-col justify-between space-y-8">
                            <!-- Quotes -->
                            <div class="relative">
                                <span class="absolute -left-4 -top-6 text-7xl font-serif text-brand-100 select-none">“</span>
                                
                                <div x-show="activeTestimonial === 0" class="space-y-4">
                                    <p class="text-base sm:text-lg italic leading-relaxed text-gray-600">
                                        "MavaPOS sangat mudah digunakan dan membantu kami mengembangkan 27 outlet dalam waktu yang singkat. Dashboard yang dapat diakses secara Real-time memudahkan kami menganalisis laporan omset penjualan dari seluruh cabang di Indonesia."
                                    </p>
                                </div>

                                <div x-show="activeTestimonial === 1" class="space-y-4" style="display: none;">
                                    <p class="text-base sm:text-lg italic leading-relaxed text-gray-600">
                                        "Aplikasi kasir ini sangat membantu dalam melakukan stock opname harian, mengecek ketersediaan bahan steak, dan memberi laporan analisis berdasarkan waktu teramai. Jadi kami tahu kapan harus menyiapkan staff tambahan."
                                    </p>
                                </div>

                                <div x-show="activeTestimonial === 2" class="space-y-4" style="display: none;">
                                    <p class="text-base sm:text-lg italic leading-relaxed text-gray-600">
                                        "Staff kasir kami melayani pelanggan lebih cepat. Di sisi manajemen, laporannya sangat rapi dan lengkap untuk evaluasi tim keuangan dan operasional. Sangat cocok untuk model bisnis hybrid retail & kopi."
                                    </p>
                                </div>
                            </div>

                            <!-- Carousel Dots / Controls -->
                            <div class="flex items-center justify-between">
                                <div class="flex gap-2">
                                    <template x-for="(t, index) in Array.from({ length: testimonialsCount })" :key="index">
                                        <button @click="activeTestimonial = index" 
                                                class="h-2 w-8 rounded-full bg-gray-200 transition-colors"
                                                :class="{'bg-brand-500': activeTestimonial === index}"></button>
                                    </template>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="activeTestimonial = (activeTestimonial === 0) ? testimonialsCount - 1 : activeTestimonial - 1" class="h-9 w-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50">
                                        ←
                                    </button>
                                    <button @click="activeTestimonial = (activeTestimonial === testimonialsCount - 1) ? 0 : activeTestimonial + 1" class="h-9 w-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50">
                                        →
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Annual Billing Promotion -->
        <section class="px-4 py-8">
            <div class="mx-auto max-w-5xl rounded-3xl bg-gradient-to-r from-brand-600 to-brand-700 p-8 sm:p-12 text-white relative overflow-hidden shadow-theme-xl">
                <!-- Glow Effect -->
                <div class="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                
                <div class="relative z-1 flex flex-col items-center justify-between gap-6 md:flex-row">
                    <div class="space-y-2 text-center md:text-left">
                        <span class="inline-block bg-white/20 px-3 py-1 rounded-full text-xs font-bold text-brand-100">PROMO HEMAT HINGGA 30%</span>
                        <h3 class="text-2xl font-bold sm:text-3xl">Langganan Mava Pro Selama 1 Tahun</h3>
                        <p class="text-sm text-brand-100/80">Hemat pengeluaran operasional hingga Rp 500.000 dibandingkan bayar bulanan.</p>
                    </div>
                    <a href="{{ route('signup') }}" class="rounded-full bg-white px-6 py-3.5 font-bold text-brand-600 shadow-theme-md transition hover:bg-brand-50 hover:scale-[1.02] shrink-0">
                        Ambil Promo Sekarang
                    </a>
                </div>
            </div>
        </section>

        <!-- Pricing Table -->
        <section id="harga" class="px-4 py-20 lg:py-28">
            <div class="mx-auto max-w-7xl">
                <!-- Title -->
                <div class="mx-auto max-w-3xl text-center space-y-4">
                    <span class="inline-flex rounded-full bg-brand-50 px-3.5 py-1 text-xs font-bold text-brand-600">HARGA TRANSPARAN</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Investasi Terjangkau untuk Pertumbuhan Usaha Anda
                    </h2>
                    <p class="text-lg text-gray-500">
                        Pilih paket berlangganan kasir online yang sesuai dengan tahapan bisnis Anda saat ini.
                    </p>
                </div>

                <!-- Price Cards -->
                <div class="mx-auto mt-16 grid max-w-4xl items-stretch gap-8 md:grid-cols-2">
                    <!-- Trial Plan -->
                    <div class="premium-card flex flex-col p-8 bg-white">
                        <h3 class="text-2xl font-bold text-gray-900">Uji Coba</h3>
                        <p class="mt-2 text-sm text-gray-500">Untuk mencoba fitur inti kasir dan menyiapkan toko pertama Anda.</p>
                        
                        <div class="my-8">
                            <span class="text-5xl font-extrabold text-gray-900">Rp 0</span>
                            <span class="text-gray-500">/ 14 hari</span>
                        </div>

                        <ul class="space-y-4 text-sm text-gray-600 mb-8 border-t border-gray-100 pt-6">
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> 1 Akun Toko / Outlet Cabang</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Maksimal 50 Produk SKU</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Transaksi POS Kasir Lengkap</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Ringkasan Laporan Harian Dasar</li>
                        </ul>

                        <a href="{{ route('signup') }}" class="mt-auto rounded-full border border-gray-200 bg-white py-3.5 text-center font-bold text-gray-700 transition hover:bg-gray-50">
                            Coba Sekarang
                        </a>
                    </div>

                    <!-- Pro Plan -->
                    <div class="premium-card flex flex-col p-8 border-2 border-brand-500 relative bg-white md:-translate-y-4 shadow-theme-xl">
                        <span class="absolute right-6 top-0 -translate-y-1/2 rounded-full bg-brand-500 px-4 py-1 text-xs font-bold uppercase tracking-wide text-white">TERPOPULER</span>
                        
                        <h3 class="text-2xl font-bold text-gray-900">Mava Pro</h3>
                        <p class="mt-2 text-sm text-gray-500">Sistem kasir online lengkap untuk bisnis aktif, retail, kafe, & salon.</p>
                        
                        <div class="my-8">
                            <span class="text-5xl font-extrabold text-gray-900">Rp 149.000</span>
                            <span class="text-gray-500">/ bulan / outlet</span>
                        </div>

                        <ul class="space-y-4 text-sm text-gray-600 mb-8 border-t border-gray-100 pt-6">
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Produk & Transaksi Kasir Tanpa Batas</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Resep & Bahan Baku Otomatis Terpotong</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Kontrol Stok Masuk, Keluar, & Opname</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Laporan Keuangan Lengkap & Unduh PDF</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Multi-Akun Kasir, Supervisor & Manajer</li>
                            <li class="flex items-start gap-3"><span class="font-bold text-brand-500">✓</span> Integrasi QRIS & Layanan Pembayaran Digital</li>
                        </ul>

                        <a href="{{ route('signup') }}" class="mt-auto rounded-full bg-brand-500 py-3.5 text-center font-bold text-white shadow-theme-md transition hover:bg-brand-600 hover:scale-[1.02]">
                            Berlangganan Mava Pro
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="px-4 py-20 lg:py-28 bg-gray-100/40">
            <div class="mx-auto max-w-4xl">
                <!-- Title -->
                <div class="text-center space-y-4">
                    <span class="inline-flex rounded-full bg-brand-50 px-3.5 py-1 text-xs font-bold text-brand-600">FAQ</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Pertanyaan yang Sering Diajukan
                    </h2>
                </div>

                <!-- FAQ Accordion -->
                <div class="mt-16 space-y-4">
                    @php
                        $faqs = [
                            [
                                'q' => 'Apakah saya perlu menginstal aplikasi khusus untuk menggunakan MavaPOS?',
                                'a' => 'Tidak perlu. MavaPOS adalah aplikasi kasir online berbasis web (Cloud-based). Anda cukup membukanya melalui browser (seperti Google Chrome atau Safari) di tablet, iPad, laptop, komputer, atau perangkat smartphone Anda.'
                            ],
                            [
                                'q' => 'Bagaimana cara kerja resep dan bahan baku otomatis di F&B?',
                                'a' => 'Di MavaPOS, Anda dapat menghubungkan menu jualan (misalnya: Kopi Susu) dengan bahan baku penyusunnya (misalnya: 150ml susu, 30ml kopi). Saat transaksi kasir selesai, sistem akan otomatis mengurangi volume bahan baku tersebut di database gudang secara real-time.'
                            ],
                            [
                                'q' => 'Apakah MavaPOS tetap berfungsi saat koneksi internet terputus?',
                                'a' => 'Ya, sistem kasir offline MavaPOS dirancang untuk tetap dapat melakukan pencatatan transaksi penjualan secara lokal. Setelah koneksi internet tersambung kembali, data transaksi kasir akan otomatis disinkronkan ke server backoffice cloud.'
                            ],
                            [
                                'q' => 'Bagaimana cara mencetak struk transaksi pelanggan?',
                                'a' => 'MavaPOS mendukung printer struk bluetooth termal umum, printer USB, serta printer jaringan (LAN/Wi-Fi). Anda cukup menyambungkan printer ke perangkat kasir Anda dan mengaktifkan fitur cetak struk dari pengaturan kasir.'
                            ]
                        ];
                    @endphp

                    @foreach ($faqs as $faq)
                        <details class="group premium-card bg-white p-6 transition hover:border-brand-500/25">
                            <summary class="flex cursor-pointer items-center justify-between gap-4 font-bold text-gray-800 focus:outline-none">
                                <span>{{ $faq['q'] }}</span>
                                <svg class="h-5 w-5 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>
                            <p class="mt-4 text-sm leading-relaxed text-gray-500">
                                {{ $faq['a'] }}
                            </p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- bottom CTA Section -->
        <section class="px-4 py-20 lg:py-28 bg-brand-900 text-white text-center relative overflow-hidden">
            <!-- Glow Background -->
            <div class="absolute left-1/2 top-1/2 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500/15 blur-3xl"></div>
            
            <div class="mx-auto max-w-4xl relative z-1 space-y-8">
                <h2 class="text-3xl font-extrabold sm:text-5xl tracking-tight leading-tight">
                    Mulai Rapikan Transaksi & Stok Bisnis Anda Hari Ini
                </h2>
                <p class="mx-auto max-w-xl text-base text-gray-300">
                    Daftar dalam 2 menit, siapkan data produk Anda, dan nikmati kemudahan rekap penjualan kasir tanpa pusing spreadsheet terpisah.
                </p>
                <div class="flex flex-col justify-center gap-4 sm:flex-row">
                    <a href="{{ route('signup') }}" class="rounded-full bg-white px-8 py-4 font-bold text-brand-900 shadow-theme-lg transition hover:bg-brand-50 hover:scale-[1.02]">
                        Buat Akun Gratis Sekarang
                    </a>
                    <a href="{{ route('signin') }}" class="rounded-full border border-white/20 bg-transparent px-8 py-4 font-bold text-white transition hover:bg-white/10">
                        Masuk Dashboard
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-white px-4 py-16 border-t border-gray-100">
        <div class="mx-auto max-w-7xl">
            <div class="grid gap-10 md:grid-cols-4">
                <!-- Brand Info -->
                <div class="space-y-4 md:col-span-2">
                    <img src="{{ asset('logo.png') }}" class="h-8 w-auto" alt="MavaPOS Logo">
                    <p class="text-sm leading-relaxed text-gray-500 max-w-sm">
                        MavaPOS adalah platform solusi kasir digital (POS) berbasis cloud untuk UMKM, F&B, toko retail, dan penyedia jasa yang ingin mengelola transaksi kasir, stok gudang, resep, dan laporan finansial dari satu sistem terpusat.
                    </p>
                </div>
                
                <!-- Nav Links -->
                <div>
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Navigasi</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-gray-500">
                        <li><a href="#fitur" class="hover:text-brand-500">Fitur Kasir</a></li>
                        <li><a href="#jenis-usaha" class="hover:text-brand-500">Solusi Bisnis</a></li>
                        <li><a href="#harga" class="hover:text-brand-500">Paket Harga</a></li>
                        <li><a href="#faq" class="hover:text-brand-500">Pusat Bantuan (FAQ)</a></li>
                    </ul>
                </div>

                <!-- Account Links -->
                <div>
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Akses Portal</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-gray-500">
                        <li><a href="{{ route('signin') }}" class="hover:text-brand-500">Masuk Backoffice</a></li>
                        <li><a href="{{ route('signup') }}" class="hover:text-brand-500">Pendaftaran Akun Baru</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Copyright -->
            <div class="mt-12 pt-8 border-t border-gray-100 flex flex-col justify-between items-center gap-4 sm:flex-row text-xs text-gray-400">
                <p>&copy; {{ date('Y') }} MavaPOS (PT Mava POS Teknologi). Hak Cipta Dilindungi.</p>
                <div class="flex gap-4">
                    <a href="{{ route('privacy-policy') }}" class="hover:text-gray-600">Kebijakan Privasi</a>
                    <span>•</span>
                    <a href="{{ route('terms-of-service') }}" class="hover:text-gray-600">Syarat & Ketentuan</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating WhatsApp Help Button -->
    <div class="fixed bottom-6 right-6 z-99999">
        <a href="https://api.whatsapp.com/send?phone=6281110697700&text=Halo%20MavaPOS!%20Saya%20ingin%20konsultasi%20mengenai%20aplikasi%20kasir%20untuk%20usaha%20saya." target="_blank" rel="noopener" class="flex items-center gap-2 rounded-full bg-green-500 px-5 py-3 text-sm font-bold text-white shadow-theme-xl transition hover:bg-green-600 hover:scale-[1.04]">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.457L0 24zm6.59-4.846c1.6.95 3.182 1.449 4.825 1.451 5.436 0 9.86-4.37 9.864-9.799.002-2.63-1.023-5.101-2.885-6.966a9.9 9.9 0 00-6.98-2.879c-5.443 0-9.866 4.372-9.87 9.802 0 1.714.47 3.387 1.357 4.881l-.997 3.641 3.791-.989zM17.15 14.39c-.3-.15-1.782-.88-2.053-.98-.271-.1-.47-.15-.667.15-.197.3-.763.96-.935 1.16-.172.2-.345.23-.645.08-1.706-.85-2.812-1.56-3.882-3.41-.284-.49.284-.45.815-1.51.09-.18.045-.34-.02-.49-.067-.15-.667-1.61-.913-2.2-.24-.58-.485-.5-.667-.51l-.57-.01c-.197 0-.516.07-.787.37-.27.3-1.03 1.01-1.03 2.47 0 1.46 1.062 2.87 1.21 3.07.147.2 2.09 3.2 5.06 4.49.707.307 1.26.49 1.69.63.71.226 1.358.194 1.87.118.571-.085 1.782-.73 2.03-1.43.248-.7.248-1.3.172-1.43-.075-.12-.27-.2-.57-.35z"/>
            </svg>
            Tanya MavaPOS
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Header shadow on scroll
    const header = document.getElementById('landing-header');
    const updateHeader = () => {
        if (header) {
            header.classList.toggle('is-scrolled', window.scrollY > 20);
        }
    };

    window.addEventListener('scroll', updateHeader, { passive: true });
    updateHeader();
</script>
@endpush
