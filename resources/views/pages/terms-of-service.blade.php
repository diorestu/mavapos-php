@extends('layouts.fullscreen-layout')

@section('content')
    <div class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-white/90">
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3">
                    <img src="{{ asset('logo.png') }}" class="h-8 w-auto" alt="MavaPOS Logo">
                </a>
                <a href="{{ route('signin') }}" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-bold text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-300">
                    Masuk
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-12 sm:py-16">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-wide text-brand-500">Dokumen Publik</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-gray-900 dark:text-white">Syarat dan Ketentuan Layanan MavaPOS</h1>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Terakhir diperbarui: {{ now()->translatedFormat('d F Y') }}</p>
                <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                    Syarat dan Ketentuan ini mengatur penggunaan situs, aplikasi backoffice, fitur POS, dan layanan lain yang disediakan oleh MavaPOS.
                </p>
            </div>

            <article class="mt-10 space-y-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 sm:p-8">
                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">1. Penerimaan Ketentuan</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Dengan membuat akun, mengakses, atau menggunakan MavaPOS, Anda menyatakan telah membaca, memahami, dan menyetujui Syarat dan Ketentuan ini serta Kebijakan Privasi MavaPOS.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">2. Akun dan Tanggung Jawab Pengguna</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Anda bertanggung jawab menjaga kerahasiaan kredensial akun, mengatur akses staf sesuai kewenangan, dan memastikan data yang dimasukkan ke sistem akurat. Aktivitas yang terjadi melalui akun Anda dianggap sebagai aktivitas yang dilakukan oleh Anda atau pihak yang Anda beri akses.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">3. Penggunaan Layanan</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">MavaPOS disediakan untuk membantu pengelolaan transaksi, produk, stok, cabang, pelanggan, laporan, dan operasional usaha. Anda dilarang menggunakan layanan untuk aktivitas ilegal, melanggar hak pihak lain, mengganggu sistem, mencoba mengakses data yang bukan milik Anda, atau menyalahgunakan integrasi pembayaran dan komunikasi.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">4. Langganan, Uji Coba, dan Pembayaran</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">MavaPOS dapat menyediakan masa uji coba, paket berbayar, atau fitur tertentu berdasarkan langganan. Harga, durasi, manfaat paket, dan metode pembayaran dapat ditampilkan pada halaman harga, invoice, atau kanal resmi MavaPOS. Akses fitur tertentu dapat dibatasi jika masa uji coba atau langganan berakhir.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">5. Data Operasional dan Cadangan</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Anda tetap bertanggung jawab atas data operasional bisnis yang dimasukkan ke MavaPOS. Kami berupaya menjaga ketersediaan dan keamanan layanan, namun Anda disarankan menyimpan catatan bisnis penting sesuai kebutuhan internal, akuntansi, dan kepatuhan hukum usaha Anda.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">6. Ketersediaan dan Perubahan Layanan</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Kami dapat melakukan pemeliharaan, pembaruan, penyesuaian fitur, atau penghentian fitur tertentu untuk meningkatkan layanan, menjaga keamanan, atau menyesuaikan kebutuhan bisnis. Kami akan berupaya memberikan pemberitahuan wajar jika terdapat perubahan material.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">7. Batasan Tanggung Jawab</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Sepanjang diperbolehkan hukum yang berlaku, MavaPOS tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, kehilangan data akibat kelalaian pengguna, gangguan pihak ketiga, atau penggunaan layanan di luar ketentuan ini.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">8. Pengakhiran Akses</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Kami dapat menangguhkan atau mengakhiri akses jika terjadi pelanggaran ketentuan, penyalahgunaan layanan, kegagalan pembayaran, risiko keamanan, atau permintaan hukum yang sah.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">9. Perubahan Ketentuan</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Syarat dan Ketentuan ini dapat diperbarui dari waktu ke waktu. Penggunaan layanan setelah perubahan berlaku berarti Anda menyetujui versi terbaru.</p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">10. Kontak</h2>
                    <p class="leading-7 text-gray-600 dark:text-gray-300">Jika Anda memiliki pertanyaan terkait Syarat dan Ketentuan ini, hubungi MavaPOS melalui WhatsApp resmi atau email dukungan yang tercantum pada kanal komunikasi MavaPOS.</p>
                </section>
            </article>
        </main>
    </div>
@endsection
