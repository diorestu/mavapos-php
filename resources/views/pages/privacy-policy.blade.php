@extends('layouts.fullscreen-layout')

@section('content')
    <div class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-white/90">
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3">
                    <img src="{{ asset('logo.png') }}" class="h-8 w-auto" alt="MavaPOS Logo">
                </a>
                <a href="{{ route('signup') }}" class="rounded-full bg-brand-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600">
                    Daftar Gratis
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-12 sm:py-16">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-brand-500">Dokumen Publik</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-gray-900 dark:text-white">Kebijakan Privasi MavaPOS</h1>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Terakhir diperbarui: {{ now()->translatedFormat('d F Y') }}</p>
                <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                    Kebijakan Privasi ini menjelaskan bagaimana MavaPOS mengumpulkan, menggunakan, menyimpan, melindungi, dan mengelola data pribadi pengguna saat menggunakan situs, aplikasi backoffice, dan layanan terkait MavaPOS.
                </p>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-[220px_1fr]">
                <aside class="hidden lg:block">
                    <nav class="sticky top-8 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <a href="#data" class="block hover:text-brand-500">Data yang dikumpulkan</a>
                        <a href="#penggunaan" class="block hover:text-brand-500">Penggunaan data</a>
                        <a href="#pembagian" class="block hover:text-brand-500">Pembagian data</a>
                        <a href="#hak" class="block hover:text-brand-500">Hak pengguna</a>
                        <a href="#kontak" class="block hover:text-brand-500">Kontak</a>
                    </nav>
                </aside>

                <article class="space-y-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 sm:p-8">
                    <section id="data" class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">1. Data yang Kami Kumpulkan</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Kami dapat mengumpulkan data akun seperti nama, alamat email, nomor telepon, nama usaha, informasi cabang, role pengguna, dan informasi autentikasi. Jika Anda menggunakan fitur operasional, kami juga memproses data produk, stok, transaksi, pelanggan, supplier, laporan, pengaturan toko, dan data pembayaran langganan.</p>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Saat menggunakan login Google, kami menerima informasi dasar dari akun Google Anda, seperti nama, alamat email, dan ID akun Google, sesuai izin yang Anda berikan.</p>
                    </section>

                    <section id="penggunaan" class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">2. Tujuan Penggunaan Data</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Data digunakan untuk membuat dan mengelola akun, menyediakan fitur POS dan backoffice, memproses transaksi, mengelola langganan, menjaga keamanan akun, memberikan dukungan pelanggan, mengirim pemberitahuan layanan, dan meningkatkan kualitas produk.</p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">3. Dasar Pemrosesan dan Persetujuan</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Kami memproses data berdasarkan kebutuhan pelaksanaan layanan, persetujuan pengguna, kepentingan yang sah untuk keamanan dan peningkatan layanan, serta kewajiban hukum yang berlaku. Anda dapat menarik persetujuan untuk pemrosesan tertentu dengan menghubungi kami, sepanjang penarikan tersebut tidak menghalangi kewajiban hukum atau pelaksanaan layanan yang masih berjalan.</p>
                    </section>

                    <section id="pembagian" class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">4. Pembagian Data kepada Pihak Ketiga</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Kami dapat membagikan data terbatas kepada penyedia infrastruktur, payment gateway, layanan autentikasi, layanan komunikasi, dan mitra teknis lain yang diperlukan untuk menjalankan layanan. Pihak ketiga tersebut hanya boleh memproses data sesuai instruksi dan kebutuhan layanan.</p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">5. Keamanan dan Retensi</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Kami menerapkan langkah keamanan teknis dan organisasi yang wajar untuk mencegah akses tidak sah, kehilangan, penyalahgunaan, atau perubahan data. Data disimpan selama akun aktif, selama diperlukan untuk tujuan layanan, atau selama diwajibkan oleh hukum dan kepentingan administrasi yang sah.</p>
                    </section>

                    <section id="hak" class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">6. Hak Pengguna</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Anda dapat meminta akses, koreksi, pembaruan, pembatasan, atau penghapusan data pribadi sesuai ketentuan hukum yang berlaku. Permintaan akan kami proses setelah verifikasi identitas dan kelayakan permintaan.</p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">7. Perubahan Kebijakan</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu. Perubahan material akan diinformasikan melalui situs, aplikasi, email, atau kanal komunikasi lain yang tersedia.</p>
                    </section>

                    <section id="kontak" class="space-y-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">8. Kontak</h2>
                        <p class="leading-7 text-gray-600 dark:text-gray-300">Untuk pertanyaan atau permintaan terkait data pribadi, hubungi MavaPOS melalui WhatsApp resmi atau email dukungan yang tercantum pada kanal komunikasi MavaPOS.</p>
                    </section>
                </article>
            </div>
        </main>
    </div>
@endsection
