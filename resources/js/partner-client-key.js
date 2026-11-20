export function partnerCredentials({ baseUrl }) {
    return {
        baseUrl,
        keys: [], name: '', environment: 'sandbox', days: 90,
        created: null, error: '', message: '', loading: false, busy: false,
        init() { this.load(); },
        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin', ...options,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...options.headers },
            });
            if (response.status === 204) return null;
            let data;
            try { data = await response.json(); }
            catch { throw new Error('Jawaban server tidak tersedia. Silakan muat ulang halaman dan masuk kembali.'); }
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Permintaan tidak dapat diproses.');
            return data;
        },
        async load() {
            this.loading = true;
            try { this.keys = (await this.request(this.baseUrl)).data; }
            catch (error) { this.error = error.message; }
            finally { this.loading = false; }
        },
        async create() {
            if (this.busy || !this.name.trim()) return;
            this.busy = true; this.error = ''; this.message = '';
            try {
                const data = await this.request(this.baseUrl, { method: 'POST', body: JSON.stringify({ name: this.name.trim(), sandbox: this.environment === 'sandbox', expires_in_days: Number(this.days) }) });
                this.created = { id: data.data.id, name: data.data.name, token: data.token, clientKey: data.client_key };
                this.name = '';
                await this.load();
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async rotate(key) {
            if (this.busy) return;
            if (key.client_key_configured && !window.confirm('Ganti Client Key? Client Key lama akan berhenti berlaku. Bearer token tetap sama.')) return;
            this.busy = true; this.error = ''; this.message = '';
            try {
                const data = await this.request(`${this.baseUrl}/${key.id}/client-key`, { method: 'POST' });
                this.created = { id: data.data.id, name: data.data.name, token: null, clientKey: data.client_key };
                await this.load();
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async revoke(key) {
            if (this.busy || !window.confirm('Cabut kredensial ini? Bearer token dan Client Key pasangannya tidak dapat dipakai lagi.')) return;
            this.busy = true; this.error = ''; this.message = '';
            try {
                await this.request(`${this.baseUrl}/${key.id}`, { method: 'DELETE' });
                if (this.created?.id === key.id) this.created = null;
                this.message = 'Kredensial dicabut.';
                await this.load();
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async copy(value) {
            try { await navigator.clipboard.writeText(value); this.message = 'Key disalin.'; }
            catch { this.error = 'Tidak dapat menyalin otomatis. Pilih teks key dan salin secara manual.'; }
        },
        expiresAt(value) {
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Makassar' }).format(date);
        },
    };
}
