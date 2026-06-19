@extends('layouts.fullscreen-layout')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

<style>
    :root {
        --md-bg: #FFFBFE;
        --md-surface: #F3EDF7;
        --md-surface-low: #E7E0EC;
        --md-primary: #6750A4;
        --md-primary-dark: #4F378B;
        --md-secondary: #E8DEF8;
        --md-secondary-on: #1D192B;
        --md-tertiary: #7D5260;
        --md-on: #1C1B1F;
        --md-on-variant: #49454F;
        --md-outline: #79747E;
        --md-ease: cubic-bezier(0.2, 0, 0, 1);
    }

    html {
        scroll-behavior: smooth;
    }

    .md3-page {
        min-height: 100vh;
        overflow-x: clip;
        background:
            radial-gradient(circle at 12% 8%, rgba(232, 222, 248, 0.9) 0, transparent 32rem),
            radial-gradient(circle at 88% 14%, rgba(125, 82, 96, 0.16) 0, transparent 28rem),
            var(--md-bg);
        color: var(--md-on);
        font-family: "Roboto", ui-sans-serif, system-ui, sans-serif;
    }

    .md3-orb {
        position: absolute;
        pointer-events: none;
        border-radius: 999px;
        filter: blur(70px);
        opacity: 0.34;
        mix-blend-mode: multiply;
    }

    .md3-header {
        background: rgba(255, 251, 254, 0.82);
        backdrop-filter: blur(18px);
        border-bottom: 1px solid rgba(121, 116, 126, 0.18);
        transition: padding 300ms var(--md-ease), box-shadow 300ms var(--md-ease);
    }

    .md3-header.is-scrolled {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
        box-shadow: 0 12px 30px rgba(28, 27, 31, 0.08);
    }

    .md3-shell {
        width: min(1180px, calc(100% - 2rem));
        margin-inline: auto;
    }

    .md3-pill {
        display: inline-flex;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 999px;
        padding: 0.75rem 1.35rem;
        font-weight: 700;
        line-height: 1;
        transition: transform 220ms var(--md-ease), background-color 220ms var(--md-ease), box-shadow 300ms var(--md-ease), color 220ms var(--md-ease);
    }

    .md3-pill:active {
        transform: scale(0.95);
    }

    .md3-pill-primary {
        background: var(--md-primary);
        color: #FFFFFF;
        box-shadow: 0 10px 22px rgba(103, 80, 164, 0.24);
    }

    .md3-pill-primary:hover {
        background: rgba(103, 80, 164, 0.9);
        box-shadow: 0 16px 34px rgba(103, 80, 164, 0.32);
    }

    .md3-pill-tonal {
        background: var(--md-secondary);
        color: var(--md-secondary-on);
    }

    .md3-pill-tonal:hover {
        background: rgba(232, 222, 248, 0.72);
    }

    .md3-pill-outline {
        color: var(--md-primary);
        border: 1px solid rgba(121, 116, 126, 0.48);
        background: rgba(255, 251, 254, 0.46);
    }

    .md3-pill-outline:hover {
        background: rgba(103, 80, 164, 0.08);
    }

    .md3-card {
        border-radius: 24px;
        background: var(--md-surface);
        box-shadow: 0 1px 3px rgba(28, 27, 31, 0.08);
        transition: transform 300ms var(--md-ease), box-shadow 300ms var(--md-ease), background-color 300ms var(--md-ease);
    }

    .md3-card:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 14px 34px rgba(28, 27, 31, 0.12);
    }

    .md3-focus:focus-visible {
        outline: 2px solid var(--md-primary);
        outline-offset: 3px;
    }

    .md3-section-title {
        max-width: 760px;
        margin-inline: auto;
        text-align: center;
    }

    .md3-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        background: var(--md-secondary);
        color: var(--md-primary-dark);
        padding: 0.45rem 0.8rem;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }

    .md3-display {
        font-size: clamp(2.45rem, 7vw, 4.85rem);
        font-weight: 700;
        line-height: 1.05;
        letter-spacing: 0;
    }

    .md3-h2 {
        font-size: clamp(2rem, 4.8vw, 3rem);
        font-weight: 700;
        line-height: 1.16;
        letter-spacing: 0;
    }

    .md3-body {
        color: var(--md-on-variant);
        font-size: 1.0625rem;
        line-height: 1.65;
    }

    .md3-hero-panel {
        position: relative;
        overflow: hidden;
        border-radius: 36px;
        background:
            radial-gradient(circle at 20% 15%, rgba(255, 255, 255, 0.68) 0, transparent 18rem),
            linear-gradient(145deg, rgba(243, 237, 247, 0.96), rgba(232, 224, 236, 0.84));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 22px 60px rgba(28, 27, 31, 0.1);
    }

    .md3-screen {
        border-radius: 32px;
        background: rgba(255, 251, 254, 0.72);
        border: 1px solid rgba(121, 116, 126, 0.22);
        box-shadow: 0 24px 55px rgba(28, 27, 31, 0.16);
        backdrop-filter: blur(12px);
    }

    .md3-step-badge {
        position: relative;
        isolation: isolate;
        display: grid;
        height: 3.5rem;
        width: 3.5rem;
        place-items: center;
        border-radius: 20px;
        background: var(--md-primary);
        color: #FFFFFF;
        font-weight: 700;
        box-shadow: 0 10px 28px rgba(103, 80, 164, 0.32);
    }

    .md3-step-badge::before {
        content: "";
        position: absolute;
        inset: -0.55rem;
        z-index: -1;
        border-radius: inherit;
        background: var(--md-primary);
        filter: blur(18px);
        opacity: 0;
        transition: opacity 300ms var(--md-ease);
    }

    .group:hover .md3-step-badge::before {
        opacity: 0.36;
    }

    @media (max-width: 767px) {
        .md3-shell {
            width: min(100% - 1.25rem, 1180px);
        }

        .md3-hero-panel {
            border-radius: 28px;
        }

        .md3-pill {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>

<div class="md3-page">
    <header id="landing-header" class="md3-header fixed left-0 right-0 top-0 z-9999 px-4 py-4">
        <div class="md3-shell flex items-center justify-between gap-4">
            <a href="#" class="md3-focus inline-flex items-center gap-3 rounded-full">
                <span class="grid h-11 w-11 place-items-center rounded-[18px] bg-[#6750A4] text-sm font-bold text-white shadow-theme-md">mP</span>
                <span class="text-xl font-bold text-[#1C1B1F]">mava<span class="text-[#6750A4]">POS</span></span>
            </a>

            <nav class="hidden items-center gap-2 rounded-full bg-[#F3EDF7]/80 p-1 text-sm font-medium md:flex" aria-label="Navigasi utama">
                <a href="#fitur" class="md3-focus rounded-full px-4 py-2 text-[#49454F] transition hover:bg-[#6750A4]/10 hover:text-[#6750A4]">Fitur</a>
                <a href="#alur" class="md3-focus rounded-full px-4 py-2 text-[#49454F] transition hover:bg-[#6750A4]/10 hover:text-[#6750A4]">Alur</a>
                <a href="#harga" class="md3-focus rounded-full px-4 py-2 text-[#49454F] transition hover:bg-[#6750A4]/10 hover:text-[#6750A4]">Harga</a>
                <a href="#faq" class="md3-focus rounded-full px-4 py-2 text-[#49454F] transition hover:bg-[#6750A4]/10 hover:text-[#6750A4]">FAQ</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('signin') }}" class="md3-focus hidden rounded-full px-4 py-2 text-sm font-bold text-[#6750A4] transition hover:bg-[#6750A4]/10 sm:inline-flex">Masuk</a>
                <a href="{{ route('signup') }}" class="md3-focus md3-pill md3-pill-primary hidden sm:inline-flex">Coba gratis</a>
            </div>
        </div>
    </header>

    <main>
        <section class="relative px-3 pb-10 pt-28 sm:px-6 lg:pt-32">
            <div class="md3-orb -left-24 top-40 h-72 w-72 bg-[#6750A4]"></div>
            <div class="md3-orb -right-20 top-20 h-80 w-80 bg-[#7D5260]"></div>
            <div class="md3-shell md3-hero-panel px-5 py-10 sm:px-10 lg:px-14 lg:py-16">
                <div class="relative z-1 grid items-center gap-10 lg:grid-cols-[0.96fr_1.04fr]">
                    <div class="space-y-7 text-center lg:text-left">
                        <span data-aos="fade-up" class="md3-kicker">Sistem kasir cloud untuk UMKM modern</span>
                        <div data-aos="fade-up" data-aos-delay="100" class="space-y-5">
                            <h1 class="md3-display">Kasir, stok, dan laporan dalam satu ruang kerja</h1>
                            <p class="md3-body mx-auto max-w-2xl lg:mx-0">
                                mavaPOS membantu toko, kafe, dan restoran melayani transaksi lebih cepat sambil menjaga stok bahan baku, resep, pelanggan, dan laporan keuangan tetap sinkron.
                            </p>
                        </div>
                        <div data-aos="fade-up" data-aos-delay="180" class="flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                            <a href="{{ route('signup') }}" class="md3-focus md3-pill md3-pill-primary">Mulai uji coba 14 hari</a>
                            <a href="#fitur" class="md3-focus md3-pill md3-pill-outline">Lihat cara kerjanya</a>
                        </div>
                        <div data-aos="fade-up" data-aos-delay="260" class="grid grid-cols-3 gap-3 pt-3">
                            <div class="rounded-[24px] bg-[#FFFBFE]/70 p-4">
                                <p class="text-2xl font-bold">&lt;3 dtk</p>
                                <p class="mt-1 text-xs font-medium text-[#49454F]">transaksi kasir</p>
                            </div>
                            <div class="rounded-[24px] bg-[#FFFBFE]/70 p-4">
                                <p class="text-2xl font-bold">14 hari</p>
                                <p class="mt-1 text-xs font-medium text-[#49454F]">uji coba gratis</p>
                            </div>
                            <div class="rounded-[24px] bg-[#FFFBFE]/70 p-4">
                                <p class="text-2xl font-bold">PDF</p>
                                <p class="mt-1 text-xs font-medium text-[#49454F]">laporan siap unduh</p>
                            </div>
                        </div>
                    </div>

                    <div data-aos="fade-left" data-aos-delay="140" class="relative">
                        <div class="absolute -left-8 -top-8 h-28 w-28 rounded-full bg-[#E8DEF8] blur-2xl" aria-hidden="true"></div>
                        <div class="absolute -bottom-10 right-8 h-32 w-48 rounded-[80px] bg-[#7D5260]/30 blur-3xl" aria-hidden="true"></div>
                        <div class="md3-screen relative p-3">
                            <div class="mb-3 flex items-center justify-between px-2">
                                <div class="flex gap-1.5" aria-hidden="true">
                                    <span class="h-3 w-3 rounded-full bg-[#7D5260]/50"></span>
                                    <span class="h-3 w-3 rounded-full bg-[#6750A4]/45"></span>
                                    <span class="h-3 w-3 rounded-full bg-[#E8DEF8]"></span>
                                </div>
                                <span class="rounded-full bg-[#E8DEF8] px-3 py-1 text-xs font-bold text-[#4F378B]">Live dashboard</span>
                            </div>
                            <img src="{{ asset('images/brand/dashboard_preview.png') }}" class="aspect-[16/10] w-full rounded-[24px] object-cover shadow-theme-lg" alt="Tampilan dashboard mavaPOS dengan ringkasan transaksi dan inventaris">
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div class="rounded-[18px] bg-[#F3EDF7] p-3">
                                    <p class="text-xs font-medium text-[#49454F]">Hari ini</p>
                                    <p class="mt-1 text-sm font-bold">Rp 8,4 jt</p>
                                </div>
                                <div class="rounded-[18px] bg-[#E8DEF8] p-3">
                                    <p class="text-xs font-medium text-[#49454F]">Produk laris</p>
                                    <p class="mt-1 text-sm font-bold">Kopi Susu</p>
                                </div>
                                <div class="rounded-[18px] bg-[#F3EDF7] p-3">
                                    <p class="text-xs font-medium text-[#49454F]">Stok aman</p>
                                    <p class="mt-1 text-sm font-bold">92%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section aria-label="Bukti sosial" class="px-4 py-8">
            <div class="md3-shell grid gap-3 sm:grid-cols-3">
                <div class="md3-card p-5 text-center">
                    <p class="text-sm font-bold text-[#6750A4]">Retail</p>
                    <p class="mt-1 text-sm text-[#49454F]">kelola stok dan pelanggan harian</p>
                </div>
                <div class="md3-card p-5 text-center">
                    <p class="text-sm font-bold text-[#6750A4]">Kafe & Resto</p>
                    <p class="mt-1 text-sm text-[#49454F]">resep otomatis potong bahan baku</p>
                </div>
                <div class="md3-card p-5 text-center">
                    <p class="text-sm font-bold text-[#6750A4]">Outlet bertumbuh</p>
                    <p class="mt-1 text-sm text-[#49454F]">laporan rapi tanpa spreadsheet manual</p>
                </div>
            </div>
        </section>

        <section id="fitur" class="px-4 py-20">
            <div class="md3-shell">
                <div data-aos="fade-up" class="md3-section-title space-y-4">
                    <span class="md3-kicker">Fitur inti</span>
                    <h2 class="md3-h2">Satu sistem untuk alur operasional yang biasanya terpisah</h2>
                    <p class="md3-body">Bangun rutinitas kasir yang lebih singkat, stok yang lebih akurat, dan laporan yang langsung bisa dipakai mengambil keputusan.</p>
                </div>

                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @php
                        $features = [
                            ['Kasir cepat', 'Proses penjualan, diskon, metode pembayaran, cetak struk, dan riwayat transaksi dari satu layar kasir yang ringkas.', 'M9 7h6m-6 4h6m-6 4h3'],
                            ['Resep & bahan baku', 'Setiap menu bisa terhubung ke bahan baku sehingga stok otomatis berkurang saat produk terjual.', 'M19 11H5m14 0v8H5v-8m14 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v2'],
                            ['Inventaris real-time', 'Pantau stok masuk, stok keluar, opname, dan bahan yang menipis sebelum mengganggu penjualan.', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10'],
                            ['Laporan keuangan', 'Lihat penjualan, pengeluaran, laba bersih, dan unduh ringkasan PDF untuk arsip atau evaluasi bulanan.', 'M9 19v-6a2 2 0 00-2-2H5v8m4 0V9a2 2 0 012-2h2v12m4 0V5a2 2 0 012-2h2v16'],
                            ['Pelanggan & supplier', 'Simpan profil pelanggan, supplier, dan riwayat relasi bisnis agar transaksi berikutnya lebih cepat.', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                            ['Pengaturan fleksibel', 'Atur mata uang, profil toko, pajak, diskon, kategori produk, dan akses pengguna sesuai cara kerja usaha.', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
                        ];
                    @endphp

                    @foreach ($features as $feature)
                        <article data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 90 }}" class="md3-card group p-6">
                            <div class="mb-6 grid h-14 w-14 place-items-center rounded-[22px] bg-[#6750A4] text-white shadow-theme-md transition group-hover:scale-105">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature[2] }}" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold">{{ $feature[0] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-[#49454F]">{{ $feature[1] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="px-4 py-12">
            <div class="md3-shell relative overflow-hidden rounded-[36px] bg-[#6750A4] p-6 text-white shadow-theme-xl sm:p-10 lg:p-14">
                <div class="absolute -left-20 -top-24 h-72 w-72 rounded-full bg-white/20 blur-3xl" aria-hidden="true"></div>
                <div class="absolute -bottom-24 right-0 h-80 w-80 rounded-full bg-[#7D5260]/50 blur-3xl" aria-hidden="true"></div>
                <div class="relative grid items-center gap-10 lg:grid-cols-[0.9fr_1.1fr]">
                    <div data-aos="fade-right" class="space-y-5">
                        <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold backdrop-blur-sm">Untuk jam sibuk</span>
                        <h2 class="md3-h2">Kurangi antrean, kurangi input ulang</h2>
                        <p class="text-base leading-7 text-white/82">Antarmuka kasir dibuat untuk ritme toko yang bergerak cepat: produk mudah dicari, stok langsung tersambung, dan laporan terbentuk tanpa rekap manual.</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-[24px] border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-2xl font-bold">1</p>
                                <p class="text-xs text-white/75">layar transaksi</p>
                            </div>
                            <div class="rounded-[24px] border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-2xl font-bold">Auto</p>
                                <p class="text-xs text-white/75">potong stok</p>
                            </div>
                            <div class="rounded-[24px] border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-2xl font-bold">PDF</p>
                                <p class="text-xs text-white/75">laporan bulanan</p>
                            </div>
                        </div>
                    </div>
                    <div data-aos="fade-left" class="rounded-[30px] border border-white/15 bg-white/10 p-2 shadow-2xl backdrop-blur-sm">
                        <img src="{{ asset('images/brand/dashboard_preview.png') }}" class="aspect-[16/10] w-full rounded-[24px] object-cover shadow-inner" alt="Preview dashboard mavaPOS">
                    </div>
                </div>
            </div>
        </section>

        <section id="alur" class="px-4 py-20">
            <div class="md3-shell">
                <div data-aos="fade-up" class="md3-section-title space-y-4">
                    <span class="md3-kicker">Cara kerja</span>
                    <h2 class="md3-h2">Mulai dari data toko, lalu biarkan sistem merapikan alurnya</h2>
                </div>
                <div class="mt-12 grid gap-5 md:grid-cols-3">
                    @foreach ([
                        ['01', 'Atur toko', 'Masukkan profil usaha, kategori, produk, bahan baku, resep, dan user kasir.'],
                        ['02', 'Jalankan transaksi', 'Kasir melayani pelanggan dari layar POS. Sistem mencatat pembayaran dan mengurangi stok.'],
                        ['03', 'Baca keputusan', 'Pemilik melihat laporan, stok menipis, penjualan terbaik, dan pengeluaran dari dashboard.'],
                    ] as $step)
                        <article data-aos="fade-up" data-aos-delay="{{ $loop->index * 120 }}" class="md3-card group p-6">
                            <div class="md3-step-badge">{{ $step[0] }}</div>
                            <h3 class="mt-7 text-2xl font-bold">{{ $step[1] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-[#49454F]">{{ $step[2] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="harga" class="px-4 py-20">
            <div class="md3-shell">
                <div data-aos="fade-up" class="md3-section-title space-y-4">
                    <span class="md3-kicker">Paket harga</span>
                    <h2 class="md3-h2">Harga jelas untuk usaha yang sedang bertumbuh</h2>
                    <p class="md3-body">Mulai dari uji coba tanpa risiko, lalu naik ke paket penuh saat operasional siap berjalan rutin.</p>
                </div>

                <div class="mx-auto mt-14 grid max-w-5xl items-stretch gap-6 md:grid-cols-2">
                    <article data-aos="fade-up" class="md3-card flex flex-col p-7">
                        <h3 class="text-2xl font-bold">Uji Coba</h3>
                        <p class="mt-2 text-sm text-[#49454F]">Untuk mencoba fitur inti dan menyiapkan toko pertama.</p>
                        <div class="my-7">
                            <span class="text-5xl font-bold">Rp 0</span>
                            <span class="text-[#49454F]">/ 14 hari</span>
                        </div>
                        <ul class="space-y-4 text-sm text-[#49454F]">
                            <li class="flex gap-3"><span class="font-bold text-[#6750A4]">✓</span> 1 akun toko atau outlet</li>
                            <li class="flex gap-3"><span class="font-bold text-[#6750A4]">✓</span> Maksimal 50 produk</li>
                            <li class="flex gap-3"><span class="font-bold text-[#6750A4]">✓</span> Fitur kasir dan transaksi POS</li>
                            <li class="flex gap-3"><span class="font-bold text-[#6750A4]">✓</span> Laporan harian dasar</li>
                        </ul>
                        <a href="{{ route('signup') }}" class="md3-focus md3-pill md3-pill-tonal mt-auto">Mulai gratis</a>
                    </article>

                    <article data-aos="fade-up" data-aos-delay="140" class="md3-card relative flex flex-col p-7 ring-2 ring-[#6750A4] md:-translate-y-4">
                        <span class="absolute right-6 top-0 -translate-y-1/2 rounded-full bg-[#6750A4] px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-white shadow-theme-md">Terpopuler</span>
                        <h3 class="text-2xl font-bold">Mava Pro</h3>
                        <p class="mt-2 text-sm text-[#49454F]">Sistem penuh untuk toko, kafe, restoran, dan retail aktif.</p>
                        <div class="my-7">
                            <span class="text-5xl font-bold">Rp 149.000</span>
                            <span class="text-[#49454F]">/ bulan</span>
                        </div>
                        <ul class="space-y-4 text-sm text-[#49454F]">
                            <li class="flex gap-3 transition hover:translate-x-1"><span class="font-bold text-[#6750A4]">✓</span> Produk dan transaksi tanpa batas</li>
                            <li class="flex gap-3 transition hover:translate-x-1"><span class="font-bold text-[#6750A4]">✓</span> Resep dan bahan baku otomatis</li>
                            <li class="flex gap-3 transition hover:translate-x-1"><span class="font-bold text-[#6750A4]">✓</span> Kontrol stok masuk dan keluar</li>
                            <li class="flex gap-3 transition hover:translate-x-1"><span class="font-bold text-[#6750A4]">✓</span> Laporan lengkap dan unduh PDF</li>
                            <li class="flex gap-3 transition hover:translate-x-1"><span class="font-bold text-[#6750A4]">✓</span> Multi-akun kasir dan manajer</li>
                        </ul>
                        <a href="{{ route('signup') }}" class="md3-focus md3-pill md3-pill-primary mt-auto">Berlangganan sekarang</a>
                    </article>
                </div>
            </div>
        </section>

        <section id="faq" class="px-4 py-20">
            <div class="md3-shell grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
                <div data-aos="fade-right" class="space-y-4">
                    <span class="md3-kicker">FAQ</span>
                    <h2 class="md3-h2">Pertanyaan sebelum mulai</h2>
                    <p class="md3-body">Jawaban singkat untuk hal yang biasanya ditanyakan pemilik usaha sebelum memindahkan operasional ke POS cloud.</p>
                </div>
                <div data-aos="fade-left" class="space-y-3">
                    @foreach ([
                        ['Apakah perlu instal aplikasi?', 'Tidak. mavaPOS berbasis web sehingga bisa diakses dari browser desktop, tablet, atau perangkat kasir.'],
                        ['Apakah cocok untuk bisnis makanan?', 'Cocok. Fitur resep dan bahan baku membantu menu F&B otomatis mengurangi stok bahan saat transaksi selesai.'],
                        ['Bisakah laporan diunduh?', 'Bisa. Laporan operasional dan keuangan tersedia dalam dashboard dan dapat diunduh sebagai PDF.'],
                        ['Bagaimana jika bisnis saya baru mulai?', 'Gunakan paket uji coba 14 hari untuk memasukkan produk, mencoba transaksi, dan melihat alur laporan.'],
                    ] as $item)
                        <details class="group rounded-[24px] bg-[#F3EDF7] p-5 transition hover:bg-[#E8DEF8]">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold">
                                {{ $item[0] }}
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#6750A4]/10 text-[#6750A4] transition group-open:rotate-45">+</span>
                            </summary>
                            <p class="mt-3 text-sm leading-6 text-[#49454F]">{{ $item[1] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="px-4 py-16">
            <div data-aos="zoom-in" class="md3-shell relative overflow-hidden rounded-[40px] bg-[#1D192B] p-8 text-center text-white shadow-theme-xl sm:p-12">
                <div class="absolute left-1/2 top-0 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#6750A4] blur-3xl" aria-hidden="true"></div>
                <div class="relative mx-auto max-w-3xl space-y-5">
                    <h2 class="md3-h2">Siapkan toko Anda untuk transaksi berikutnya</h2>
                    <p class="text-base leading-7 text-white/76">Mulai dari produk pertama, proses transaksi pertama, lalu lihat laporan pertama tanpa spreadsheet tambahan.</p>
                    <div class="mx-auto flex max-w-xl flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ route('signup') }}" class="md3-focus md3-pill bg-white text-[#1D192B] hover:bg-white/90">Buat akun gratis</a>
                        <a href="{{ route('signin') }}" class="md3-focus md3-pill border border-white/20 text-white hover:bg-white/10">Masuk dashboard</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="px-4 py-10">
        <div class="md3-shell border-t border-[#79747E]/20 pt-8">
            <div class="grid gap-8 md:grid-cols-[1.3fr_0.7fr_0.7fr]">
                <div>
                    <a href="#" class="md3-focus inline-flex items-center gap-3 rounded-full">
                        <span class="grid h-10 w-10 place-items-center rounded-[16px] bg-[#6750A4] text-xs font-bold text-white">mP</span>
                        <span class="text-lg font-bold">mava<span class="text-[#6750A4]">POS</span></span>
                    </a>
                    <p class="mt-4 max-w-md text-sm leading-6 text-[#49454F]">Platform kasir digital untuk UMKM, restoran, kafe, dan retail yang ingin menjalankan transaksi, stok, dan laporan dari satu sistem.</p>
                </div>
                <div>
                    <h4 class="text-sm font-bold">Navigasi</h4>
                    <ul class="mt-3 space-y-2 text-sm text-[#49454F]">
                        <li><a href="#fitur" class="hover:text-[#6750A4]">Fitur</a></li>
                        <li><a href="#alur" class="hover:text-[#6750A4]">Cara kerja</a></li>
                        <li><a href="#harga" class="hover:text-[#6750A4]">Harga</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-bold">Akun</h4>
                    <ul class="mt-3 space-y-2 text-sm text-[#49454F]">
                        <li><a href="{{ route('signin') }}" class="hover:text-[#6750A4]">Masuk</a></li>
                        <li><a href="{{ route('signup') }}" class="hover:text-[#6750A4]">Daftar gratis</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 flex flex-col justify-between gap-3 border-t border-[#79747E]/20 pt-6 text-xs text-[#49454F] sm:flex-row">
                <p>&copy; {{ date('Y') }} mavaPOS. Hak cipta dilindungi undang-undang.</p>
                <p>Dibuat dengan Laravel & Tailwind CSS</p>
            </div>
        </div>
    </footer>
</div>
@endsection

@push('scripts')
<script>
    const header = document.getElementById('landing-header');
    const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 28);

    window.addEventListener('scroll', updateHeader, { passive: true });
    updateHeader();
</script>
@endpush
