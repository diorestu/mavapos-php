export async function submitPosGateway({ endpoint, payload, context, storage = window.localStorage, cryptoProvider = crypto, fetcher = fetch, timeoutMs = 25000 }) {
    if (!cryptoProvider.subtle) throw new Error('Checkout gateway memerlukan HTTPS atau localhost.');
    const bytes = await cryptoProvider.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify({ context, payload })));
    const hash = Array.from(new Uint8Array(bytes), byte => byte.toString(16).padStart(2, '0')).join('');
    const storageKey = `mava:gateway:${context.userId}:${context.branchId}`;
    let draft;
    try {
        draft = JSON.parse(storage.getItem(storageKey) || 'null');
        if (!draft || draft.hash !== hash) draft = { hash, id: cryptoProvider.randomUUID() };
        storage.setItem(storageKey, JSON.stringify(draft));
    } catch {
        throw new Error('Penyimpanan checkout tidak tersedia. Aktifkan penyimpanan browser sebelum menggunakan gateway.');
    }
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetcher(endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ ...payload, client_transaction_id: draft.id }),
            signal: controller.signal,
        });
        let data;
        try { data = await response.json(); }
        catch { throw new Error('Jawaban pembayaran belum lengkap. Ulangi checkout yang sama.'); }
        if (!response.ok) throw new Error(data.message || 'Pembayaran gateway belum dapat disiapkan. Ulangi checkout yang sama.');
        if (!data.id || !data.redirect_url) throw new Error('Jawaban checkout belum lengkap. Ulangi checkout yang sama.');
        storage.removeItem(storageKey);
        return data;
    } catch (error) {
        if (error.name === 'AbortError') throw new Error('Koneksi terlalu lama. Ulangi checkout yang sama untuk memeriksa pesanan Anda.');
        throw error;
    } finally {
        clearTimeout(timeout);
    }
}
