<section x-show="activeTab === 'api'" x-cloak x-data="partnerCredentials({baseUrl: @js(route('settings.partner-keys.index'))})"
    class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" aria-labelledby="partner-credentials-title">
    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <h2 id="partner-credentials-title" class="text-sm font-semibold text-gray-800 dark:text-white/90">Kredensial REST API Pihak Ketiga</h2>
        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Setiap integrasi menggunakan satu pasangan Bearer token dan Client Key. Semua request wajib mengirim keduanya.</p>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-900 dark:text-gray-300"><code>Authorization: Bearer {token}
X-Client-Key: {client_key}</code></pre>
    </div>
    <div class="space-y-4 p-4">
        <p x-cloak x-show="error" x-text="error" role="alert" class="rounded-lg bg-error-50 p-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-300"></p>
        <p x-cloak x-show="message" x-text="message" role="status" class="text-sm text-success-700 dark:text-success-300"></p>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_150px_150px_auto]">
            <label for="partner-key-name" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Nama integrasi
                <input id="partner-key-name" x-model="name" maxlength="120" placeholder="Contoh: Dashboard Penjualan" @keydown.enter.prevent="create()"
                    class="mt-1 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
            </label>
            <label for="partner-key-environment" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Mode
                <select id="partner-key-environment" x-model="environment" class="mt-1 h-11 w-full rounded-lg border border-gray-300 bg-transparent pl-3 pr-8 text-sm dark:border-gray-700 dark:text-white/90"><option value="sandbox">Sandbox</option><option value="production">Produksi</option></select>
            </label>
            <label for="partner-key-days" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Masa berlaku (hari)
                <input id="partner-key-days" type="number" min="1" max="365" x-model.number="days" @keydown.enter.prevent="create()" class="mt-1 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
            </label>
            <button type="button" @click="create()" :disabled="busy || !name.trim() || days < 1 || days > 365" class="min-h-11 self-end rounded-lg bg-brand-500 px-4 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Buat Pasangan Kredensial</button>
        </div>
        <div x-cloak x-show="created" class="space-y-3 rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
            <p class="text-sm font-semibold text-success-800 dark:text-success-300" x-text="created?.name"></p>
            <p class="text-xs leading-5 text-success-700 dark:text-success-300">Salin dan simpan sekarang. Nilai lengkap hanya ditampilkan sekali dan tidak dapat dilihat lagi setelah halaman ditutup.</p>
            <div x-show="created?.token">
                <label for="partner-created-bearer" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Bearer token</label>
                <div class="flex flex-col gap-2 sm:flex-row"><input id="partner-created-bearer" type="text" readonly :value="created?.token || ''" autocomplete="off" spellcheck="false" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" /><button type="button" @click="copy(created.token)" class="min-h-11 rounded-lg border border-gray-300 px-3 text-xs font-semibold dark:text-gray-300">Salin Bearer</button></div>
            </div>
            <div>
                <label for="partner-created-client" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Client Key · header X-Client-Key</label>
                <div class="flex flex-col gap-2 sm:flex-row"><input id="partner-created-client" type="text" readonly :value="created?.clientKey || ''" autocomplete="off" spellcheck="false" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" /><button type="button" @click="copy(created.clientKey)" class="min-h-11 rounded-lg border border-gray-300 px-3 text-xs font-semibold dark:text-gray-300">Salin Client Key</button></div>
            </div>
            <p x-show="created && !created.token" class="text-xs text-success-700 dark:text-success-300">Gunakan Bearer token yang sudah tersimpan sebelumnya. Jika Bearer tidak tersimpan, buat pasangan kredensial baru.</p>
            <button type="button" @click="created = null" class="min-h-11 text-xs font-semibold text-success-800 underline dark:text-success-300">Sudah Disimpan</button>
        </div>
        <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">Token lama yang belum memiliki Client Key belum bisa mengakses Partner API. Klik Buat Client Key pada barisnya. Penggantian Client Key tidak mengganti Bearer token.</p>
        <div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-xs"><thead class="bg-gray-50 text-gray-500 dark:bg-gray-900"><tr><th class="px-3 py-3">Integrasi / mode</th><th class="px-3 py-3">Client Key</th><th class="px-3 py-3">Berlaku sampai (WITA)</th><th class="px-3 py-3">Aksi</th></tr></thead>
            <tbody><template x-for="key in keys" :key="key.id"><tr class="border-t border-gray-100 dark:border-gray-800"><td class="px-3 py-3"><span class="font-semibold text-gray-800 dark:text-white/90" x-text="key.name"></span><span class="mt-1 block text-gray-500" x-text="(key.sandbox ? 'Sandbox' : 'Produksi') + ' · ' + (key.active ? 'Aktif' : key.revoked_at ? 'Dicabut' : 'Kedaluwarsa')"></span><span class="block font-mono text-gray-500" x-text="key.prefix + '…'"></span></td><td class="px-3 py-3 text-gray-600 dark:text-gray-300"><span x-text="key.client_key_configured ? key.client_key_prefix + '…' : 'Belum dibuat'"></span></td><td class="px-3 py-3 text-gray-600 dark:text-gray-300" x-text="expiresAt(key.expires_at)"></td><td class="px-3 py-3"><div x-show="key.active" class="flex flex-wrap gap-2"><button type="button" @click="rotate(key)" :disabled="busy" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold text-gray-700 disabled:opacity-50 dark:text-gray-300" x-text="key.client_key_configured ? 'Ganti Client Key' : 'Buat Client Key'"></button><button type="button" @click="revoke(key)" :disabled="busy" class="min-h-11 rounded-lg border border-error-200 px-3 font-semibold text-error-700 disabled:opacity-50 dark:text-error-300">Cabut</button></div></td></tr></template>
            <tr x-show="!keys.length"><td colspan="4" class="px-3 py-5 text-gray-500" x-text="loading ? 'Memuat kredensial…' : 'Belum ada kredensial Partner API.'"></td></tr></tbody>
        </table></div>
    </div>
</section>
