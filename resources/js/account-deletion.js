import { createIndexedDbStorage, purgePosOfflineShell } from './pos-offline.js';

export function accountDeletionManager(userId, options = {}, dependencies = {}) {
    const storage = dependencies.storage || createIndexedDbStorage();
    const purgeShell = dependencies.purgeShell || purgePosOfflineShell;
    const isOnline = dependencies.isOnline || (() => globalThis.navigator?.onLine !== false);

    return {
        open: Boolean(options.open),
        googleVerified: Boolean(options.googleVerified),
        verification: options.verification || 'password',
        busy: false,
        queueError: '',
        async submit(event) {
            event.preventDefault();
            if (this.busy) return;
            this.busy = true;
            this.queueError = '';
            if (!isOnline()) {
                this.queueError = 'Hubungkan perangkat ke internet untuk menghapus akun.';
                this.busy = false;
                return;
            }
            try {
                const orders = await storage.list();
                if (orders.some(order => String(order.context?.userId) === String(userId) && order.status !== 'cancelled')) {
                    this.queueError = 'Masih ada transaksi perangkat yang belum selesai. Selesaikan sinkronisasi atau periksa antrean yang ditolak sebelum menghapus akun.';
                    this.busy = false;
                    return;
                }
                await purgeShell();
                event.target.submit();
            } catch (error) {
                this.queueError = 'Pemeriksaan data perangkat belum berhasil. Aktifkan penyimpanan browser dan coba lagi.';
                this.busy = false;
            }
        },
    };
}
