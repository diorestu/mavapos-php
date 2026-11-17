export function billingPaymentWatcher({ payments = [], initialCheckout = null } = {}) {
    return {
        payments,
        initialCheckout,
        popupOpen: false,
        finishing: false,
        snapUnavailable: false,
        paymentError: '',
        loadHandler: null,
        checking: false,
        lastChecked: 0,
        rounds: 0,
        timer: null,
        controller: null,
        destroyed: false,

        init() {
            if (this.initialCheckout) {
                if (document.readyState === 'complete') this.openInitialPayment();
                else {
                    this.loadHandler = () => this.openInitialPayment();
                    window.addEventListener('load', this.loadHandler, { once: true });
                }
            }
            if (!this.payments.length) return;
            this.check();
            this.timer = window.setInterval(() => {
                if (this.rounds >= 20) {
                    window.clearInterval(this.timer);
                    this.timer = null;
                    return;
                }
                this.check();
            }, 15000);
        },

        destroy() {
            this.destroyed = true;
            if (this.loadHandler) window.removeEventListener('load', this.loadHandler);
            this.controller?.abort();
            if (this.timer !== null) window.clearInterval(this.timer);
            this.timer = null;
        },

        openInitialPayment() {
            if (this.destroyed || !this.initialCheckout) return;
            const checkout = this.initialCheckout;
            this.initialCheckout = null;
            this.pay(checkout.token, checkout.verifyUrl);
        },

        pay(token, verifyUrl) {
            if (this.destroyed || this.popupOpen || this.finishing || !token || !verifyUrl) return;
            if (typeof window.snap?.pay !== 'function') {
                this.snapUnavailable = true;
                this.paymentError = 'Popup pembayaran belum siap. Coba lagi atau buka halaman pembayaran.';
                return;
            }
            this.paymentError = '';
            this.snapUnavailable = false;
            this.popupOpen = true;
            let finished = false;
            const verify = () => {
                if (finished || this.destroyed) return;
                finished = true;
                this.popupOpen = false;
                this.finishing = true;
                window.location.assign(verifyUrl);
            };
            try {
                window.snap.pay(token, { onSuccess: verify, onPending: verify, onError: verify, onClose: verify });
            } catch {
                this.popupOpen = false;
                this.snapUnavailable = true;
                this.paymentError = 'Popup pembayaran tidak dapat dibuka. Silakan gunakan halaman pembayaran.';
            }
        },

        async check() {
            if (this.destroyed || this.popupOpen || this.finishing || this.checking || !this.payments.length || document.visibilityState === 'hidden') return;
            if (Date.now() - this.lastChecked < 5000) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrf) return;
            this.checking = true;
            this.lastChecked = Date.now();
            this.rounds++;
            try {
                for (const payment of this.payments) {
                    if (this.destroyed || this.popupOpen || this.finishing) return;
                    this.controller = new AbortController();
                    const timeout = setTimeout(() => this.controller?.abort(), 25000);
                    try {
                        const response = await fetch(payment.url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            signal: this.controller.signal,
                        });
                        if (!response.ok) continue;
                        const data = await response.json();
                        if (!this.destroyed && !this.popupOpen && !this.finishing && typeof data.payment_status === 'string' && data.payment_status !== payment.status) {
                            window.location.reload();
                            return;
                        }
                    } catch {
                        // The manual status check remains available if polling cannot reach the server.
                    } finally {
                        clearTimeout(timeout);
                        this.controller = null;
                    }
                }
            } finally {
                this.checking = false;
            }
        },
    };
}
