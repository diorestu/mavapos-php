import './bootstrap';
import Alpine from 'alpinejs';
import Toastify from 'toastify-js';
import 'toastify-js/src/toastify.css';

window.Alpine = Alpine;

let apexChartsLoader;
window.loadApexCharts = async () => {
    if (!window.ApexCharts) {
        apexChartsLoader ??= import('apexcharts').then((module) => {
            window.ApexCharts = module.default;

            return window.ApexCharts;
        });
        await apexChartsLoader;
    }

    return window.ApexCharts;
};

let flatpickrLoader;
window.loadFlatpickr = async () => {
    if (!window.flatpickr) {
        flatpickrLoader ??= Promise.all([
            import('flatpickr'),
            import('flatpickr/dist/flatpickr.min.css'),
        ]).then(([module]) => {
            window.flatpickr = module.default;

            return window.flatpickr;
        });
        await flatpickrLoader;
    }

    return window.flatpickr;
};

const toastThemes = {
    success: 'linear-gradient(135deg, #039855, #12b76a)',
    error: 'linear-gradient(135deg, #b42318, #f04438)',
    info: 'linear-gradient(135deg, #344054, #475467)',
};

window.notify = (message, type = 'success') => {
    if (!message) {
        return;
    }

    Toastify({
        text: message,
        duration: 3200,
        close: true,
        gravity: 'top',
        position: 'right',
        stopOnFocus: true,
        style: {
            background: toastThemes[type] || toastThemes.info,
            borderRadius: '10px',
            boxShadow: '0 12px 30px rgba(15, 23, 42, 0.18)',
            fontSize: '13px',
            fontWeight: '600',
            lineHeight: '1.4',
            maxWidth: '360px',
        },
    }).showToast();
};

const notify = window.notify;

Alpine.data('globalSearch', (endpoint = '') => ({
    endpoint,
    query: '',
    results: [],
    isOpen: false,
    isLoading: false,
    error: '',
    abortController: null,

    get hasQuery() {
        return this.query.trim().length > 0;
    },

    get showEmptyState() {
        return this.hasQuery && !this.isLoading && !this.error && this.results.length === 0;
    },

    focusSearch() {
        this.$refs.searchInput?.focus();
        this.isOpen = this.hasQuery;
    },

    async search() {
        const keyword = this.query.trim();

        if (!keyword) {
            this.results = [];
            this.error = '';
            this.isOpen = false;
            return;
        }

        this.abortController?.abort();
        this.abortController = new AbortController();
        this.isLoading = true;
        this.error = '';
        this.isOpen = true;

        try {
            const url = new URL(this.endpoint, window.location.origin);
            url.searchParams.set('q', keyword);

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                signal: this.abortController.signal,
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.error = payload.message || 'Pencarian gagal dimuat.';
                this.results = [];
                return;
            }

            this.results = payload.results || [];
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.error = 'Pencarian gagal dimuat.';
                this.results = [];
            }
        } finally {
            this.isLoading = false;
        }
    },

    openResult(result) {
        if (!result?.url) {
            return;
        }

        window.location.href = result.url;
    },

    goToFirstResult() {
        if (this.results.length > 0) {
            this.openResult(this.results[0]);
            return;
        }

        if (this.hasQuery) {
            window.location.href = `/sales?search=${encodeURIComponent(this.query.trim())}`;
        }
    },
}));

Alpine.data('salesDateRange', (initialFrom = '', initialTo = '') => ({
    dateFrom: initialFrom || '',
    dateTo: initialTo || '',
    picker: null,

    async mount(input) {
        if (!input) {
            return;
        }

        this.$nextTick(async () => {
            const flatpickr = await window.loadFlatpickr();
            this.picker = flatpickr(input, {
                mode: 'range',
                dateFormat: 'd M Y',
                defaultDate: [this.dateFrom, this.dateTo].filter(Boolean),
                locale: {
                    rangeSeparator: ' sampai ',
                    firstDayOfWeek: 1,
                },
                onChange: (selectedDates) => {
                    this.dateFrom = selectedDates[0] ? this.toDateValue(selectedDates[0]) : '';
                    this.dateTo = selectedDates[1] ? this.toDateValue(selectedDates[1]) : this.dateFrom;
                },
            });
        });
    },

    destroy() {
        this.picker?.destroy();
        this.picker = null;
    },

    toDateValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    },
}));

Alpine.data('bluetoothPrinterTest', () => ({
    serviceUuid: '000018f0-0000-1000-8000-00805f9b34fb',
    characteristicUuid: '00002af1-0000-1000-8000-00805f9b34fb',
    namePrefix: '',
    chunkSize: 120,
    chunkDelay: 50,
    device: null,
    server: null,
    characteristic: null,
    isBusy: false,
    logs: [],
    receiptText: [
        '            MAVAPOS             ',
        '       Jl. Contoh No. 10        ',
        '================================',
        '    TEST PRINT WEB BLUETOOTH    ',
        '================================',
        'No Nota : INV/2026/0001',
        'Tanggal : ' + new Date().toLocaleString('id-ID'),
        'Kasir   : Test User',
        'Bayar   : Tunai',
        '--------------------------------',
        'Kopi Susu Aren          Rp18.000',
        '  1 x Rp18.000',
        'Roti Bakar              Rp15.000',
        '  1 x Rp15.000',
        '--------------------------------',
        'Subtotal                Rp33.000',
        'Diskon                  -Rp5.000',
        '================================',
        'TOTAL                   Rp28.000',
        'Dibayar                 Rp30.000',
        'Kembali                  Rp2.000',
        '================================',
        '      Terima kasih atas         ',
        '        Kunjungan Anda          ',
    ].join('\n'),

    get isSupported() {
        return Boolean(navigator.bluetooth);
    },

    get connectionLabel() {
        if (!this.device) {
            return 'Belum terhubung';
        }

        return this.device.gatt?.connected ? 'Terhubung' : 'Terputus';
    },

    get deviceName() {
        return this.device?.name || this.device?.id || '-';
    },

    log(message) {
        const time = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        this.logs.unshift(`[${time}] ${message}`);
        this.logs = this.logs.slice(0, 80);
    },

    async connect() {
        if (!this.isSupported) {
            this.log('Web Bluetooth tidak tersedia di browser ini.');
            notify('Web Bluetooth tidak tersedia di browser ini.', 'error');
            return;
        }

        this.isBusy = true;

        try {
            this.disconnect(false);
            const filters = this.namePrefix.trim()
                ? [{ namePrefix: this.namePrefix.trim() }]
                : undefined;
            const requestOptions = filters
                ? { filters, optionalServices: [this.serviceUuid] }
                : { acceptAllDevices: true, optionalServices: [this.serviceUuid] };

            this.log('Membuka pemilih perangkat Bluetooth...');
            this.device = await navigator.bluetooth.requestDevice(requestOptions);
            this.device.addEventListener('gattserverdisconnected', () => {
                this.characteristic = null;
                this.server = null;
                this.log('Printer terputus.');
            });

            this.log(`Menghubungkan ke ${this.deviceName}...`);
            this.server = await this.device.gatt.connect();
            const service = await this.server.getPrimaryService(this.serviceUuid);
            this.characteristic = await service.getCharacteristic(this.characteristicUuid);
            this.log('Printer siap menerima data.');
            notify('Printer Bluetooth terhubung.');
        } catch (error) {
            this.characteristic = null;
            this.server = null;
            this.log(`Gagal konek: ${error.message || error}`);
            notify(error.message || 'Gagal menghubungkan printer.', 'error');
        } finally {
            this.isBusy = false;
        }
    },

    disconnect(showLog = true) {
        if (this.device?.gatt?.connected) {
            this.device.gatt.disconnect();
        }

        this.characteristic = null;
        this.server = null;

        if (showLog) {
            this.log('Koneksi printer diputuskan.');
        }
    },

    async printSample() {
        if (!this.characteristic) {
            this.log('Printer belum terhubung.');
            notify('Hubungkan printer terlebih dahulu.', 'error');
            return;
        }

        this.isBusy = true;

        try {
            const payload = this.buildEscPosPayload(this.receiptText);
            await this.writeInChunks(payload);
            this.log(`Print terkirim (${payload.length} bytes).`);
            notify('Test print berhasil dikirim.');
        } catch (error) {
            this.log(`Print gagal: ${error.message || error}`);
            notify(error.message || 'Test print gagal.', 'error');
        } finally {
            this.isBusy = false;
        }
    },

    buildEscPosPayload(text) {
        const encoder = new TextEncoder();
        const init = [0x1b, 0x40];
        const alignLeft = [0x1b, 0x61, 0x00];
        const feed = [0x0a, 0x0a, 0x0a];
        const cut = [0x1d, 0x56, 0x42, 0x00];
        const body = Array.from(encoder.encode(`${text}\n`));

        return new Uint8Array([...init, ...alignLeft, ...body, ...feed, ...cut]);
    },

    async writeInChunks(payload) {
        const size = Math.max(20, Math.min(Number(this.chunkSize) || 120, 512));

        for (let offset = 0; offset < payload.length; offset += size) {
            const chunk = payload.slice(offset, offset + size);

            if (typeof this.characteristic.writeValueWithoutResponse === 'function') {
                await this.characteristic.writeValueWithoutResponse(chunk);
            } else {
                await this.characteristic.writeValue(chunk);
            }

            if (this.chunkDelay > 0) {
                await new Promise((resolve) => setTimeout(resolve, this.chunkDelay));
            }
        }
    },
}));

Alpine.data('iminPrinterTest', () => ({
    host: '127.0.0.1',
    port: 8081,
    socket: null,
    statusResolver: null,
    isBusy: false,
    logs: [],
    receiptText: [
        '            MAVAPOS             ',
        '  TEST PRINT IMIN INNERPRINTER  ',
        '================================',
        'No Nota : INV/2026/0001',
        'Tanggal : ' + new Date().toLocaleString('id-ID'),
        'Kasir   : Test User',
        'Bayar   : Tunai',
        '--------------------------------',
        'Kopi Susu Aren          Rp18.000',
        '  1 x Rp18.000',
        'Roti Bakar              Rp15.000',
        '  1 x Rp15.000',
        '--------------------------------',
        'Subtotal                Rp33.000',
        'Diskon                  -Rp5.000',
        '================================',
        'TOTAL                   Rp28.000',
        'Dibayar                 Rp30.000',
        'Kembali                  Rp2.000',
        '================================',
        '      Terima kasih atas         ',
        '        Kunjungan Anda          ',
    ].join('\n'),

    get endpoint() {
        return `ws://${this.host || '127.0.0.1'}:${Number(this.port) || 8081}/websocket`;
    },

    get isConnected() {
        return this.socket?.readyState === WebSocket.OPEN;
    },

    get connectionLabel() {
        return this.isConnected ? 'Terhubung' : 'Belum terhubung';
    },

    log(message) {
        const time = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        this.logs.unshift(`[${time}] ${message}`);
        this.logs = this.logs.slice(0, 80);
    },

    async connect() {
        this.isBusy = true;

        try {
            this.disconnect(false);
            this.log(`Menghubungkan ke ${this.endpoint}...`);
            this.socket = await this.openSocket();
            this.socket.onclose = () => this.log('Koneksi IMIN terputus.');
            this.socket.onerror = () => this.log('Koneksi IMIN error.');
            this.socket.onmessage = (event) => this.handleMessage(event);
            await this.initializePrinter();
            notify('IMIN InnerPrinter terhubung.');
        } catch (error) {
            this.socket = null;
            this.log(`Gagal konek IMIN: ${error.message || error}`);
            notify(error.message || 'Gagal menghubungkan IMIN InnerPrinter.', 'error');
        } finally {
            this.isBusy = false;
        }
    },

    openSocket() {
        return new Promise((resolve, reject) => {
            const SocketClass = window.WebSocket || window.MozWebSocket;

            if (!SocketClass) {
                reject(new Error('Browser tidak mendukung WebSocket.'));
                return;
            }

            const socket = new SocketClass(this.endpoint);
            const timeout = setTimeout(() => {
                socket.close();
                reject(new Error('Print service IMIN tidak merespons di 127.0.0.1:8081.'));
            }, 5000);

            socket.onopen = () => {
                clearTimeout(timeout);
                resolve(socket);
            };
            socket.onerror = () => {
                clearTimeout(timeout);
                reject(new Error('Print service IMIN tidak tersedia. Pastikan halaman dibuka dari tablet IMIN.'));
            };
        });
    },

    disconnect(showLog = true) {
        if (this.socket) {
            this.socket.close();
        }

        this.socket = null;
        this.statusResolver = null;

        if (showLog) {
            this.log('Koneksi IMIN diputuskan.');
        }
    },

    async printSample() {
        if (!this.isConnected) {
            notify('Hubungkan IMIN InnerPrinter terlebih dahulu.', 'error');
            return;
        }

        this.isBusy = true;

        try {
            await this.initializePrinter();
            const status = await this.getPrinterStatus();

            if (Number(status.value) !== 0) {
                throw new Error(`Status printer tidak normal: ${status.text || status.value}`);
            }

            await this.sendCommands([
                ['', 6, 1],
                ['', 7, 28],
                ['', 9, 1],
                ['MAVAPOS\n', 12],
                ['', 6, 0],
                ['', 7, 24],
                ['', 9, 0],
                ...this.receiptText.split('\n').map((line) => [`${line}\n`, 12]),
                ['', 4, 120],
                ['', 5],
            ]);
            this.log('Test print IMIN dikirim.');
            notify('Test print IMIN dikirim.');
        } catch (error) {
            this.log(`Print IMIN gagal: ${error.message || error}`);
            notify(error.message || 'Test print IMIN gagal.', 'error');
        } finally {
            this.isBusy = false;
        }
    },

    async initializePrinter() {
        this.sendCommand('SPI', 1);
        await this.sleep(250);
        const status = await this.getPrinterStatus();

        if (Number(status.value) === 0) {
            this.log('IMIN InnerPrinter siap menerima test print.');
            return;
        }

        this.log(`Status IMIN: ${status.text || status.value}`);
    },

    getPrinterStatus() {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                this.statusResolver = null;
                reject(new Error('Tidak ada balasan status dari IMIN InnerPrinter.'));
            }, 5000);

            this.statusResolver = (data) => {
                clearTimeout(timeout);
                this.statusResolver = null;
                resolve({
                    ...data.data,
                    text: this.printerStatusText(data.data?.value),
                });
            };

            this.sendCommand('SPI', 2);
        });
    },

    handleMessage(event) {
        if (event.data === 'request') {
            this.sendCommand('ping');
            return;
        }

        let data = null;

        try {
            data = JSON.parse(event.data);
        } catch (error) {
            this.log(`Balasan IMIN tidak valid: ${event.data}`);
            return;
        }

        if (data?.data?.text === 'ping') {
            this.sendCommand('ping');
            return;
        }

        if (data?.type === 2 && this.statusResolver) {
            this.statusResolver(data);
        }
    },

    async sendCommands(commands) {
        for (const command of commands) {
            this.sendCommand(...command);
            await this.sleep(60);
        }
    },

    sendCommand(text = '', type = 0, value = -1, extra = {}) {
        if (!this.isConnected) {
            throw new Error('WebSocket IMIN belum terhubung.');
        }

        this.socket.send(JSON.stringify({
            data: {
                text,
                value,
                labelData: {},
                ...extra,
            },
            type,
        }));
    },

    printerStatusText(value) {
        switch (String(value)) {
            case '0':
                return 'Normal';
            case '3':
                return 'Head printer terbuka';
            case '7':
                return 'Kertas habis';
            case '8':
                return 'Kertas hampir habis';
            case '99':
                return 'Printer error';
            default:
                return 'Printer tidak terhubung atau belum menyala';
        }
    },

    sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    },
}));

Alpine.data('productManager', (initialProducts = [], initialCategories = [], initialFilters = {}) => ({
    createProductModal: false,
    editProductModal: false,
    detailProductModal: false,
    detailProduct: null,
    editingProductSku: '',
    products: initialProducts,
    categoryOptions: initialCategories,
    currentPage: 1,
    perPage: 4,
    formError: '',
    editFormError: '',
    filters: {
        search: initialFilters.search || '',
        category: initialFilters.category || '',
        status: initialFilters.status || '',
        stockMin: '',
    },
    draft: {
        name: '',
        sku: '',
        category: '',
        barcode: '',
        buyPrice: '',
        sellPrice: '',
        stock: '',
        minStock: '',
        description: '',
    },
    editDraft: {
        name: '',
        sku: '',
        category: '',
        barcode: '',
        buyPrice: '',
        sellPrice: '',
        stock: '',
        minStock: '',
        description: '',
    },

    get filteredProducts() {
        return this.products.filter((product) => {
            const keyword = this.normalize(this.filters.search);
            const matchesKeyword = !keyword ||
                this.normalize(product.name).includes(keyword) ||
                this.normalize(product.sku).includes(keyword);
            const matchesCategory = !this.filters.category ||
                this.normalize(product.categoryCode) === this.filters.category;
            const matchesStatus = !this.filters.status ||
                this.normalize(product.status).replace(/\s+/g, '-') === this.filters.status;
            const minimumStock = Number(this.filters.stockMin);
            const matchesStock = this.filters.stockMin === '' || Number(product.stock) >= minimumStock;

            return matchesKeyword && matchesCategory && matchesStatus && matchesStock;
        });
    },

    get totalPages() {
        return Math.max(1, Math.ceil(this.filteredProducts.length / this.perPage));
    },

    get paginatedProducts() {
        this.currentPage = Math.min(this.currentPage, this.totalPages);
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredProducts.slice(start, start + this.perPage);
    },

    get fromItem() {
        if (this.filteredProducts.length === 0) {
            return 0;
        }

        return (this.currentPage - 1) * this.perPage + 1;
    },

    get toItem() {
        return Math.min(this.currentPage * this.perPage, this.filteredProducts.length);
    },

    get activeCategoryOptions() {
        return this.categoryOptions.filter((category) => this.normalize(category.status) === 'aktif');
    },

    normalize(value) {
        return String(value || '').toLowerCase().trim();
    },

    categoryLabel(value) {
        return this.categoryOptions.find((category) => category.code === value)?.name || 'Umum';
    },

    categoryValue(value) {
        const normalizedValue = this.normalize(value);

        return this.categoryOptions.find((category) => this.normalize(category.name) === normalizedValue)?.code || '';
    },

    parseRupiah(value) {
        return String(value || '').replace(/[^\d]/g, '');
    },

    formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(Number(value || 0)).replace(/\s/g, '');
    },

    productStatus(stock, minStock) {
        const currentStock = Number(stock || 0);
        const threshold = Number(minStock || 0);

        if (currentStock <= 0) {
            return 'Habis';
        }

        if (threshold > 0 && currentStock <= threshold) {
            return 'Stok Menipis';
        }

        return 'Aktif';
    },

    statusClass(status) {
        return {
            Aktif: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            'Stok Menipis': 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-orange-400',
            Habis: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
        }[status] || 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400';
    },

    resetFilters() {
        this.filters = {
            search: '',
            category: '',
            status: '',
            stockMin: '',
        };
        this.currentPage = 1;
    },

    resetDraft() {
        this.formError = '';
        this.draft = {
            name: '',
            sku: '',
            category: '',
            barcode: '',
            buyPrice: '',
            sellPrice: '',
            stock: '',
            minStock: '',
            description: '',
        };
    },

    resetEditDraft() {
        this.editFormError = '';
        this.editingProductSku = '';
        this.editDraft = {
            name: '',
            sku: '',
            category: '',
            barcode: '',
            buyPrice: '',
            sellPrice: '',
            stock: '',
            minStock: '',
            description: '',
        };
    },

    openCreateModal() {
        this.resetDraft();
        this.createProductModal = true;
    },

    closeCreateModal() {
        this.createProductModal = false;
        this.resetDraft();
    },

    openEditModal(product) {
        this.editFormError = '';
        this.editingProductSku = product.sku;
        this.editDraft = {
            name: product.name || '',
            sku: product.sku || '',
            category: this.categoryValue(product.category),
            barcode: product.barcode || '',
            buyPrice: this.parseRupiah(product.buyPrice),
            sellPrice: this.parseRupiah(product.price),
            stock: product.stock ?? '',
            minStock: product.minStock ?? '',
            description: product.description || '',
        };
        this.editProductModal = true;
    },

    closeEditModal() {
        this.editProductModal = false;
        this.resetEditDraft();
    },

    openDetailModal(product) {
        this.detailProduct = product;
        this.detailProductModal = true;
    },

    closeDetailModal() {
        this.detailProductModal = false;
        this.detailProduct = null;
    },

    async addProduct() {
        this.formError = '';

        if (!this.draft.name.trim() || !this.draft.sku.trim() || this.draft.sellPrice === '') {
            this.formError = 'Nama produk, SKU, dan harga jual wajib diisi.';
            return;
        }

        const response = await fetch('/products', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(this.draft),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.formError = payload.message || 'Produk gagal dibuat.';
            notify(this.formError, 'error');
            return;
        }

        const payload = await response.json();
        this.products.unshift(payload.product);
        this.currentPage = 1;
        this.closeCreateModal();
        notify(payload.message || 'Produk berhasil dibuat.');
    },

    async updateProduct() {
        this.editFormError = '';

        if (!this.editDraft.name.trim() || !this.editDraft.sku.trim() || this.editDraft.sellPrice === '') {
            this.editFormError = 'Nama produk, SKU, dan harga jual wajib diisi.';
            return;
        }

        const response = await fetch(`/products/${encodeURIComponent(this.editingProductSku)}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(this.editDraft),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.editFormError = payload.message || 'Produk gagal diperbarui.';
            notify(this.editFormError, 'error');
            return;
        }

        const payload = await response.json();
        const productIndex = this.products.findIndex((product) => product.sku === this.editingProductSku);

        if (productIndex !== -1) {
            this.products.splice(productIndex, 1, payload.product);
        }

        this.closeEditModal();
        notify(payload.message || 'Produk berhasil diperbarui.');
    },

    async deleteProduct(product) {
        if (!product?.sku || !window.confirm(`Hapus produk ${product.name}?`)) {
            return;
        }

        const response = await fetch(`/products/${encodeURIComponent(product.sku)}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            notify(payload.message || 'Produk gagal dihapus.', 'error');
            return;
        }

        this.products = this.products.filter((item) => item.sku !== product.sku);
        this.currentPage = Math.min(this.currentPage, this.totalPages);
        notify(payload.message || 'Produk berhasil dihapus.');
    },

    goToPage(page) {
        this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages);
    },

    firstPage() {
        this.goToPage(1);
    },

    nextPage() {
        this.goToPage(this.currentPage + 1);
    },

    previousPage() {
        this.goToPage(this.currentPage - 1);
    },

    lastPage() {
        this.goToPage(this.totalPages);
    },
}));

Alpine.data('productCategoryManager', (initialCategories = [], initialFilters = {}) => ({
    createCategoryModal: false,
    editCategoryModal: false,
    detailCategoryModal: false,
    detailCategory: null,
    editingCategoryCode: '',
    categories: initialCategories,
    currentPage: 1,
    perPage: 4,
    formError: '',
    editFormError: '',
    filters: {
        search: initialFilters.search || '',
        status: initialFilters.status || '',
    },
    draft: {
        name: '',
        code: '',
        status: 'aktif',
        productCount: '',
        description: '',
    },
    editDraft: {
        name: '',
        code: '',
        status: 'aktif',
        productCount: '',
        description: '',
    },

    get filteredCategories() {
        return this.categories.filter((category) => {
            const keyword = this.normalize(this.filters.search);
            const matchesKeyword = !keyword ||
                this.normalize(category.name).includes(keyword) ||
                this.normalize(category.code).includes(keyword);
            const matchesStatus = !this.filters.status ||
                this.statusValue(category.status) === this.filters.status;

            return matchesKeyword && matchesStatus;
        });
    },

    get totalPages() {
        return Math.max(1, Math.ceil(this.filteredCategories.length / this.perPage));
    },

    get paginatedCategories() {
        this.currentPage = Math.min(this.currentPage, this.totalPages);
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredCategories.slice(start, start + this.perPage);
    },

    get fromItem() {
        if (this.filteredCategories.length === 0) {
            return 0;
        }

        return (this.currentPage - 1) * this.perPage + 1;
    },

    get toItem() {
        return Math.min(this.currentPage * this.perPage, this.filteredCategories.length);
    },

    normalize(value) {
        return String(value || '').toLowerCase().trim();
    },

    slugify(value) {
        return this.normalize(value)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },

    statusLabel(value) {
        return {
            aktif: 'Aktif',
            nonaktif: 'Nonaktif',
        }[value] || 'Aktif';
    },

    statusValue(value) {
        return {
            aktif: 'aktif',
            nonaktif: 'nonaktif',
        }[this.normalize(value)] || 'aktif';
    },

    statusClass(status) {
        return {
            Aktif: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            Nonaktif: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        }[status] || 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400';
    },

    resetFilters() {
        this.filters = {
            search: '',
            status: '',
        };
        this.currentPage = 1;
    },

    resetDraft() {
        this.formError = '';
        this.draft = {
            name: '',
            code: '',
            status: 'aktif',
            productCount: '',
            description: '',
        };
    },

    resetEditDraft() {
        this.editFormError = '';
        this.editingCategoryCode = '';
        this.editDraft = {
            name: '',
            code: '',
            status: 'aktif',
            productCount: '',
            description: '',
        };
    },

    openCreateModal() {
        this.resetDraft();
        this.createCategoryModal = true;
    },

    closeCreateModal() {
        this.createCategoryModal = false;
        this.resetDraft();
    },

    openEditModal(category) {
        this.editFormError = '';
        this.editingCategoryCode = category.code;
        this.editDraft = {
            name: category.name || '',
            code: category.code || '',
            status: this.statusValue(category.status),
            productCount: category.productCount ?? '',
            description: category.description || '',
        };
        this.editCategoryModal = true;
    },

    closeEditModal() {
        this.editCategoryModal = false;
        this.resetEditDraft();
    },

    openDetailModal(category) {
        this.detailCategory = category;
        this.detailCategoryModal = true;
    },

    closeDetailModal() {
        this.detailCategoryModal = false;
        this.detailCategory = null;
    },

    async addCategory() {
        this.formError = '';

        if (!this.draft.name.trim()) {
            this.formError = 'Nama kategori wajib diisi.';
            return;
        }

        const code = this.draft.code.trim() || this.slugify(this.draft.name);

        if (!code) {
            this.formError = 'Kode kategori wajib diisi.';
            return;
        }

        const response = await fetch('/product-categories', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                ...this.draft,
                code,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.formError = payload.message || 'Kategori produk gagal dibuat.';
            notify(this.formError, 'error');
            return;
        }

        const payload = await response.json();
        this.categories.unshift(payload.category);
        this.currentPage = 1;
        this.closeCreateModal();
        notify(payload.message || 'Kategori produk berhasil dibuat.');
    },

    async updateCategory() {
        this.editFormError = '';

        if (!this.editDraft.name.trim() || !this.editDraft.code.trim()) {
            this.editFormError = 'Nama kategori dan kode wajib diisi.';
            return;
        }

        const response = await fetch(`/product-categories/${encodeURIComponent(this.editingCategoryCode)}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(this.editDraft),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.editFormError = payload.message || 'Kategori produk gagal diperbarui.';
            notify(this.editFormError, 'error');
            return;
        }

        const payload = await response.json();
        const categoryIndex = this.categories.findIndex((category) => category.code === this.editingCategoryCode);

        if (categoryIndex !== -1) {
            this.categories.splice(categoryIndex, 1, payload.category);
        }

        this.closeEditModal();
        notify(payload.message || 'Kategori produk berhasil diperbarui.');
    },

    async deleteCategory(category) {
        if (!category?.code || !window.confirm(`Hapus kategori ${category.name}?`)) {
            return;
        }

        const response = await fetch(`/product-categories/${encodeURIComponent(category.code)}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            notify(payload.message || 'Kategori produk gagal dihapus.', 'error');
            return;
        }

        this.categories = this.categories.filter((item) => item.code !== category.code);
        this.currentPage = Math.min(this.currentPage, this.totalPages);
        notify(payload.message || 'Kategori produk berhasil dihapus.');
    },

    goToPage(page) {
        this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages);
    },

    firstPage() {
        this.goToPage(1);
    },

    nextPage() {
        this.goToPage(this.currentPage + 1);
    },

    previousPage() {
        this.goToPage(this.currentPage - 1);
    },

    lastPage() {
        this.goToPage(this.totalPages);
    },
}));

Alpine.data('contactManager', (initialItems = [], routePath = '', entityLabel = '', entityName = '', entityPlural = '', initialFilters = {}) => ({
    createModal: false,
    editModal: false,
    detailModal: false,
    detailItem: null,
    editingCode: '',
    items: initialItems,
    routePath,
    entityLabel,
    entityName,
    entityPlural,
    currentPage: 1,
    perPage: 4,
    formError: '',
    editFormError: '',
    filters: {
        search: initialFilters.search || '',
        status: initialFilters.status || '',
    },
    draft: {
        name: '',
        code: '',
        phone: '',
        email: '',
        status: 'aktif',
        address: '',
    },
    editDraft: {
        name: '',
        code: '',
        phone: '',
        email: '',
        status: 'aktif',
        address: '',
    },

    get filteredItems() {
        return this.items.filter((item) => {
            const keyword = this.normalize(this.filters.search);
            const matchesKeyword = !keyword ||
                this.normalize(item.name).includes(keyword) ||
                this.normalize(item.code).includes(keyword) ||
                this.normalize(item.phone).includes(keyword) ||
                this.normalize(item.email).includes(keyword);
            const matchesStatus = !this.filters.status ||
                this.statusValue(item.status) === this.filters.status;

            return matchesKeyword && matchesStatus;
        });
    },

    get totalPages() {
        return Math.max(1, Math.ceil(this.filteredItems.length / this.perPage));
    },

    get paginatedItems() {
        this.currentPage = Math.min(this.currentPage, this.totalPages);
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredItems.slice(start, start + this.perPage);
    },

    get fromItem() {
        if (this.filteredItems.length === 0) {
            return 0;
        }

        return (this.currentPage - 1) * this.perPage + 1;
    },

    get toItem() {
        return Math.min(this.currentPage * this.perPage, this.filteredItems.length);
    },

    normalize(value) {
        return String(value || '').toLowerCase().trim();
    },

    statusValue(value) {
        return {
            aktif: 'aktif',
            nonaktif: 'nonaktif',
        }[this.normalize(value)] || 'aktif';
    },

    statusClass(status) {
        return {
            Aktif: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            Nonaktif: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        }[status] || 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400';
    },

    fieldLabel(field) {
        return {
            name: 'Nama*',
            code: 'Kode*',
            phone: 'Telepon',
            email: 'Email',
        }[field] || field;
    },

    resetDraft() {
        this.formError = '';
        this.draft = {
            name: '',
            code: '',
            phone: '',
            email: '',
            status: 'aktif',
            address: '',
        };
    },

    resetEditDraft() {
        this.editFormError = '';
        this.editingCode = '';
        this.editDraft = {
            name: '',
            code: '',
            phone: '',
            email: '',
            status: 'aktif',
            address: '',
        };
    },

    openCreateModal() {
        this.resetDraft();
        this.createModal = true;
    },

    closeCreateModal() {
        this.createModal = false;
        this.resetDraft();
    },

    openEditModal(item) {
        this.editFormError = '';
        this.editingCode = item.code;
        this.editDraft = {
            name: item.name || '',
            code: item.code || '',
            phone: item.phone || '',
            email: item.email || '',
            status: this.statusValue(item.status),
            address: item.address || '',
        };
        this.editModal = true;
    },

    closeEditModal() {
        this.editModal = false;
        this.resetEditDraft();
    },

    openDetailModal(item) {
        this.detailItem = item;
        this.detailModal = true;
    },

    closeDetailModal() {
        this.detailModal = false;
        this.detailItem = null;
    },

    async addItem() {
        this.formError = '';

        if (!this.draft.name.trim() || !this.draft.code.trim()) {
            this.formError = `Nama dan kode ${this.entityLabel} wajib diisi.`;
            return;
        }

        const response = await fetch(this.routePath, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(this.draft),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.formError = payload.message || `${this.entityLabel} gagal dibuat.`;
            notify(this.formError, 'error');
            return;
        }

        const payload = await response.json();
        this.items.unshift(payload.item);
        this.currentPage = 1;
        this.closeCreateModal();
        notify(payload.message || `${this.entityLabel} berhasil dibuat.`);
    },

    async updateItem() {
        this.editFormError = '';

        if (!this.editDraft.name.trim() || !this.editDraft.code.trim()) {
            this.editFormError = `Nama dan kode ${this.entityLabel} wajib diisi.`;
            return;
        }

        const response = await fetch(`${this.routePath}/${encodeURIComponent(this.editingCode)}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(this.editDraft),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.editFormError = payload.message || `${this.entityLabel} gagal diperbarui.`;
            notify(this.editFormError, 'error');
            return;
        }

        const payload = await response.json();
        const itemIndex = this.items.findIndex((item) => item.code === this.editingCode);

        if (itemIndex !== -1) {
            this.items.splice(itemIndex, 1, payload.item);
        }

        this.closeEditModal();
        notify(payload.message || `${this.entityLabel} berhasil diperbarui.`);
    },

    goToPage(page) {
        this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages);
    },

    nextPage() {
        this.goToPage(this.currentPage + 1);
    },

    previousPage() {
        this.goToPage(this.currentPage - 1);
    },
}));

Alpine.data('inventoryManager', (initialItems = [], initialMovements = [], initialFilters = {}) => ({
    editModal: false,
    movementModal: false,
    editingSku: '',
    movementType: 'in',
    items: initialItems,
    movements: initialMovements,
    currentPage: 1,
    perPage: 4,
    editFormError: '',
    movementFormError: '',
    filters: {
        search: initialFilters.search || '',
        status: initialFilters.status || '',
    },
    editDraft: {
        name: '',
        sku: '',
        stock: '',
        minStock: '',
    },
    movementDraft: {
        name: '',
        sku: '',
        quantity: '',
        reference: '',
        note: '',
    },

    get filteredItems() {
        return this.items.filter((item) => {
            const keyword = this.normalize(this.filters.search);
            const matchesKeyword = !keyword ||
                this.normalize(item.name).includes(keyword) ||
                this.normalize(item.sku).includes(keyword) ||
                this.normalize(item.category).includes(keyword);
            const matchesStatus = !this.filters.status ||
                this.normalize(item.status).replace(/\s+/g, '-') === this.filters.status;

            return matchesKeyword && matchesStatus;
        });
    },

    get totalPages() {
        return Math.max(1, Math.ceil(this.filteredItems.length / this.perPage));
    },

    get paginatedItems() {
        this.currentPage = Math.min(this.currentPage, this.totalPages);
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredItems.slice(start, start + this.perPage);
    },

    get fromItem() {
        if (this.filteredItems.length === 0) {
            return 0;
        }

        return (this.currentPage - 1) * this.perPage + 1;
    },

    get toItem() {
        return Math.min(this.currentPage * this.perPage, this.filteredItems.length);
    },

    get movementTypeLabel() {
        return this.movementType === 'in' ? 'Stok Masuk' : 'Stok Keluar';
    },

    normalize(value) {
        return String(value || '').toLowerCase().trim();
    },

    statusClass(status) {
        return {
            Aktif: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            'Stok Menipis': 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-orange-400',
            Habis: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
        }[status] || 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400';
    },

    openEditModal(item) {
        this.editFormError = '';
        this.editingSku = item.sku;
        this.editDraft = {
            name: item.name || '',
            sku: item.sku || '',
            stock: item.stock ?? '',
            minStock: item.minStock ?? '',
        };
        this.editModal = true;
    },

    openMovementModal(type, item) {
        this.movementType = type === 'out' ? 'out' : 'in';
        this.movementFormError = '';
        this.movementDraft = {
            name: item.name || '',
            sku: item.sku || '',
            quantity: '',
            reference: '',
            note: '',
        };
        this.movementModal = true;
    },

    closeEditModal() {
        this.editModal = false;
        this.editFormError = '';
        this.editingSku = '';
        this.editDraft = {
            name: '',
            sku: '',
            stock: '',
            minStock: '',
        };
    },

    closeMovementModal() {
        this.movementModal = false;
        this.movementFormError = '';
        this.movementType = 'in';
        this.movementDraft = {
            name: '',
            sku: '',
            quantity: '',
            reference: '',
            note: '',
        };
    },

    async updateStock() {
        this.editFormError = '';

        if (this.editDraft.stock === '') {
            this.editFormError = 'Stok wajib diisi.';
            return;
        }

        const response = await fetch(`/inventory/${encodeURIComponent(this.editingSku)}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                stock: this.editDraft.stock,
                minStock: this.editDraft.minStock,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.editFormError = payload.message || 'Stok gagal diperbarui.';
            notify(this.editFormError, 'error');
            return;
        }

        const payload = await response.json();
        const itemIndex = this.items.findIndex((item) => item.sku === this.editingSku);

        if (itemIndex !== -1) {
            this.items.splice(itemIndex, 1, payload.item);
        }

        this.closeEditModal();
        notify(payload.message || 'Stok berhasil diperbarui.');
    },

    async saveMovement() {
        this.movementFormError = '';

        if (this.movementDraft.quantity === '' || Number(this.movementDraft.quantity) < 1) {
            this.movementFormError = 'Jumlah wajib diisi minimal 1.';
            return;
        }

        const response = await fetch(`/inventory/${encodeURIComponent(this.movementDraft.sku)}/${this.movementType}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                quantity: this.movementDraft.quantity,
                reference: this.movementDraft.reference,
                note: this.movementDraft.note,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            this.movementFormError = payload.message || `${this.movementTypeLabel} gagal dicatat.`;
            notify(this.movementFormError, 'error');
            return;
        }

        const payload = await response.json();
        const itemIndex = this.items.findIndex((item) => item.sku === this.movementDraft.sku);

        if (itemIndex !== -1) {
            this.items.splice(itemIndex, 1, payload.item);
        }

        this.movements.unshift(payload.movement);
        this.movements = this.movements.slice(0, 8);
        this.closeMovementModal();
        notify(payload.message || `${this.movementTypeLabel} berhasil dicatat.`);
    },

    goToPage(page) {
        this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages);
    },

    nextPage() {
        this.goToPage(this.currentPage + 1);
    },

    previousPage() {
        this.goToPage(this.currentPage - 1);
    },
}));

Alpine.data('posManager', (initialItems = [], initialCategories = [], initialShift = null, blockingShift = null, endpoints = {}) => ({
    items: initialItems,
    categories: initialCategories,
    shift: initialShift,
    blockingShift,
    endpoints,
    sopModal: false,
    startModal: !initialShift && !blockingShift,
    closeModal: false,
    openingCashAmount: '',
    openingNote: '',
    closingNote: '',
    shiftError: '',
    checkoutError: '',
    shiftLoading: false,
    checkoutLoading: false,
    receiptModal: false,
    lastReceipt: null,
    query: '',
    activeCategory: '',
    cart: [],
    paymentMethod: 'cash',
    paidAmount: '',
    discount: '',
    printPreferences: {
        autoPrint: false,
        closeAfterPrint: false,
    },

    init() {
        this.loadPrintPreferences();
    },

    get filteredItems() {
        const keyword = this.normalize(this.query);

        return this.items.filter((item) => {
            const matchesKeyword = !keyword ||
                this.normalize(item.name).includes(keyword) ||
                this.normalize(item.sku).includes(keyword) ||
                this.normalize(item.barcode).includes(keyword);
            const matchesCategory = !this.activeCategory || item.category === this.activeCategory;

            return matchesKeyword && matchesCategory && Number(item.stock) > 0;
        });
    },

    get favoriteItems() {
        return this.items.filter((item) => item.isFavorite && Number(item.stock) > 0).slice(0, 8);
    },

    get subtotal() {
        return this.cart.reduce((total, item) => total + (Number(item.price) * Number(item.quantity)), 0);
    },

    get discountNumber() {
        return this.numberFromInput(this.discount);
    },

    get discountValue() {
        return Math.min(this.discountNumber, this.subtotal);
    },

    get total() {
        return Math.max(0, this.subtotal - this.discountValue);
    },

    get paid() {
        return this.numberFromInput(this.paidAmount);
    },

    get change() {
        if (this.paymentMethod !== 'cash') {
            return 0;
        }

        return Math.max(0, this.paid - this.total);
    },

    get remaining() {
        if (this.paymentMethod !== 'cash') {
            return 0;
        }

        return Math.max(0, this.total - this.paid);
    },

    get canCheckout() {
        return Boolean(this.shift) && !this.checkoutLoading && this.cart.length > 0 && (this.paymentMethod !== 'cash' || this.paid >= this.total);
    },

    normalize(value) {
        return String(value || '').toLowerCase().trim();
    },

    formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(Number(value || 0)).replace(/\s/g, '');
    },

    numberFromInput(value) {
        return Number(String(value || '').replace(/[^\d]/g, ''));
    },

    formatInputNumber(value) {
        const number = this.numberFromInput(value);

        return number > 0 ? new Intl.NumberFormat('id-ID').format(number) : '';
    },

    onMoneyInput(field, event) {
        const digits = String(event.target.value || '').replace(/[^\d]/g, '');
        this[field] = digits;
        event.target.value = this.formatInputNumber(digits);
    },

    paymentLabel(method) {
        return {
            cash: 'Tunai',
            qris: 'QRIS',
            card: 'Kartu',
        }[method] || method || '-';
    },

    printerModeLabel(mode) {
        return {
            browser: 'Browser Print',
            bluetooth: 'Web Bluetooth',
            imin_inner_printer: 'IMIN InnerPrinter',
        }[mode] || 'IMIN InnerPrinter';
    },

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    stockLabel(item) {
        const stock = Number(item.stock || 0);

        if (stock <= 0) {
            return 'Habis';
        }

        if (stock <= 5) {
            return `${stock} tersisa`;
        }

        return `Stok ${stock}`;
    },

    addItem(item) {
        if (Number(item.stock || 0) <= 0) {
            return;
        }

        const current = this.cart.find((cartItem) => cartItem.id === item.id);

        if (current) {
            this.increase(current.id);
            return;
        }

        this.cart.push({
            id: item.id,
            name: item.name,
            sku: item.sku,
            price: Number(item.price),
            stock: Number(item.stock),
            quantity: 1,
        });
    },

    increase(id) {
        const item = this.cart.find((cartItem) => cartItem.id === id);

        if (!item || item.quantity >= item.stock) {
            return;
        }

        item.quantity += 1;
    },

    decrease(id) {
        const item = this.cart.find((cartItem) => cartItem.id === id);

        if (!item) {
            return;
        }

        item.quantity -= 1;

        if (item.quantity <= 0) {
            this.remove(id);
        }
    },

    remove(id) {
        this.cart = this.cart.filter((item) => item.id !== id);
    },

    clearCart() {
        this.cart = [];
        this.discount = '';
        this.paidAmount = '';
    },

    closeReceiptModal() {
        this.receiptModal = false;
    },

    closeSopModal() {
        this.sopModal = false;
    },

    loadPrintPreferences() {
        try {
            const saved = JSON.parse(localStorage.getItem('mava_pos_print_preferences') || '{}');
            this.printPreferences = {
                autoPrint: saved.autoPrint,
                closeAfterPrint: saved.closeAfterPrint,
            };
        } catch (error) {
            this.printPreferences = {
                autoPrint: undefined,
                closeAfterPrint: undefined,
            };
        }
    },

    savePrintPreferences() {
        localStorage.setItem('mava_pos_print_preferences', JSON.stringify(this.printPreferences));
    },

    async printReceipt() {
        if (!this.lastReceipt) {
            return;
        }

        const receipt = this.lastReceipt;
        const receiptOptions = receipt.receipt || {};
        const printerOptions = receipt.printer || {};
        const connectionMode = printerOptions.connection_mode || 'imin_inner_printer';

        if (connectionMode === 'imin_inner_printer') {
            try {
                await this.printIminReceipt(receipt);
                notify('Struk berhasil dikirim ke IMIN InnerPrinter.');

                if (this.effectivePrintPreference('closeAfterPrint', printerOptions.close_after_print)) {
                    this.closeReceiptModal();
                }
            } catch (error) {
                notify(error.message || 'Gagal mencetak ke IMIN InnerPrinter.', 'error');
            }

            return;
        }

        if (connectionMode === 'bluetooth') {
            try {
                await this.printBluetoothReceipt(receipt);
                notify('Struk berhasil dikirim ke printer Bluetooth.');

                if (this.effectivePrintPreference('closeAfterPrint', printerOptions.close_after_print)) {
                    this.closeReceiptModal();
                }
            } catch (error) {
                notify(error.message || 'Gagal mencetak ke printer Bluetooth.', 'error');
            }

            return;
        }

        const paperWidth = receiptOptions.paper_width === '80' ? '80mm' : '58mm';
        const showStoreAddress = receiptOptions.show_store_address !== false;
        const showCashier = receiptOptions.show_cashier !== false;
        const store = receipt.store || {};
        const storeName = store.name || 'MavaPOS';
        const storeTagline = store.tagline || '';
        const storeAddress = store.address || '';
        const storeInstagram = store.instagram || '';
        const typography = this.receiptTypography();
        const storeMetaHtml = [
            storeTagline,
            showStoreAddress ? storeAddress : '',
            storeInstagram,
        ].filter(Boolean).map((line) => `<p class="center muted store-line">${this.escapeHtml(line)}</p>`).join('');
        const cashierHtml = showCashier
            ? `<div class="row"><span>Kasir</span><span>${this.escapeHtml(receipt.cashier || '-')}</span></div>`
            : '';
        const footerNote = receiptOptions.footer_note || 'Terima kasih atas kunjungan Anda.';
        const itemRows = (receipt.items || []).map((item) => `
            <div class="item-row">
                <div class="item-main">
                    <strong class="item-name">${this.escapeHtml(item.name)}</strong>
                    <strong class="item-total">${this.formatRupiah(item.line_total)}</strong>
                </div>
                <div class="item-meta">
                    <span>${this.escapeHtml(item.sku || '-')}</span>
                    <span>${Number(item.quantity || 0)} x ${this.formatRupiah(item.unit_price)}</span>
                </div>
            </div>
        `).join('');
        const printWindow = window.open('', 'mava_receipt_print', 'width=420,height=640');

        if (!printWindow) {
            notify('Popup print diblokir browser. Izinkan popup untuk mencetak nota.', 'error');
            return;
        }

        printWindow.document.write(`
            <!doctype html>
            <html>
                <head>
                    <meta charset="utf-8">
                    <title>Nota ${this.escapeHtml(receipt.invoice_number)}</title>
                    <style>
                        * { box-sizing: border-box; }
                        body {
                            width: ${paperWidth};
                            margin: 0 auto;
                            padding: 10mm 6mm;
                            color: #111827;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: ${typography.body}px;
                            line-height: 1.36;
                        }
                        h1 { margin: 0; font-size: ${typography.heading}px; line-height: 1.2; text-align: center; }
                        .muted { color: #6b7280; }
                        .center { text-align: center; }
                        .store-line { margin: 2px 0 0; font-size: ${typography.small}px; }
                        .section-title { margin: 10px 0 6px; font-size: ${typography.small}px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; text-align: center; color: #374151; }
                        .meta { margin-top: 8px; border-top: 1.5px solid #d1d5db; padding-top: 8px; }
                        .meta .row { padding: 2px 0; }
                        .meta .row span:first-child { color: #6b7280; }
                        .meta .row span:last-child, .meta .row strong:last-child { font-weight: 500; }
                        .row, .totals div { display: flex; justify-content: space-between; gap: 8px; }
                        .row span:last-child, .row strong:last-child, .totals span:last-child { text-align: right; }
                        .items { margin-top: 10px; border-top: 1.5px solid #d1d5db; }
                        .item-row { padding: 7px 0; border-bottom: 1px dotted #e5e7eb; }
                        .item-main { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; font-size: ${typography.item}px; line-height: 1.28; }
                        .item-name { min-width: 0; flex: 1 1 auto; text-align: left; overflow-wrap: anywhere; font-weight: 700; }
                        .item-total { flex: 0 0 auto; text-align: right; white-space: nowrap; font-weight: 700; }
                        .item-meta { margin-top: 4px; display: flex; justify-content: space-between; gap: 8px; color: #4b5563; font-size: ${typography.meta}px; line-height: 1.3; }
                        .item-meta span:last-child { text-align: right; }
                        strong, span { display: block; }
                        .totals { margin-top: 10px; border-top: 2px solid #111827; border-bottom: 2px solid #111827; padding: 8px 0; }
                        .grand { margin: 8px 0 4px; padding: 5px 8px; font-size: ${typography.total}px; font-weight: 700; background: #f9fafb; border-radius: 4px; letter-spacing: 0.02em; }
                        .footer { margin-top: 12px; border-top: 1px solid #d1d5db; padding-top: 8px; text-align: center; font-style: italic; color: #6b7280; }
                        @page { margin: 0; size: ${paperWidth} auto; }
                    </style>
                </head>
                <body>
                    <h1>${this.escapeHtml(storeName)}</h1>
                    ${storeMetaHtml}
                    <div class="meta">
                        <div class="row"><span>No Nota</span><strong>${this.escapeHtml(receipt.invoice_number)}</strong></div>
                        <div class="row"><span>Tanggal</span><span>${this.escapeHtml(receipt.sold_at || '-')}</span></div>
                        ${cashierHtml}
                        <div class="row"><span>Pembayaran</span><span>${this.escapeHtml(this.paymentLabel(receipt.payment_method))}</span></div>
                    </div>
                    <div class="items">${itemRows}</div>
                    <div class="totals">
                        <div class="grand"><span>Total</span><span>${this.formatRupiah(receipt.total)}</span></div>
                        <div><span>Dibayar</span><span>${this.formatRupiah(receipt.paid_amount)}</span></div>
                        <div><span>Kembali</span><span>${this.formatRupiah(receipt.change_amount)}</span></div>
                    </div>
                    <p class="footer muted">${this.escapeHtml(footerNote)}</p>
                    <script>
                        window.addEventListener('load', () => {
                            const images = Array.from(document.images);
                            const imageLoads = images.map((image) => {
                                if (image.complete) {
                                    return Promise.resolve();
                                }

                                return new Promise((resolve) => {
                                    image.addEventListener('load', resolve, { once: true });
                                    image.addEventListener('error', resolve, { once: true });
                                });
                            });

                            Promise.all(imageLoads).finally(() => {
                                window.focus();
                                setTimeout(() => window.print(), 250);
                            });
                        });
                    <\/script>
                </body>
            </html>
        `);
        printWindow.document.close();

        if (this.effectivePrintPreference('closeAfterPrint', printerOptions.close_after_print)) {
            this.closeReceiptModal();
        }
    },

    async printBluetoothReceipt(receipt) {
        const printerOptions = receipt.printer || {};
        const serviceUuid = printerOptions.bluetooth_service_uuid;
        const characteristicUuid = printerOptions.bluetooth_characteristic_uuid;

        if (!navigator.bluetooth) {
            throw new Error('Web Bluetooth tidak tersedia di browser ini.');
        }

        if (!serviceUuid || !characteristicUuid) {
            throw new Error('UUID printer Bluetooth belum diatur di Pengaturan.');
        }

        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: [serviceUuid],
        });
        const server = await device.gatt.connect();
        const service = await server.getPrimaryService(serviceUuid);
        const characteristic = await service.getCharacteristic(characteristicUuid);
        const payload = this.buildBluetoothReceiptPayload(receipt);

        try {
            await this.writeBluetoothChunks(characteristic, payload);
        } finally {
            device.gatt?.disconnect();
        }
    },

    buildBluetoothReceiptPayload(receipt) {
        const receiptOptions = receipt.receipt || {};
        const store = receipt.store || {};
        const width = receiptOptions.paper_width === '80' ? 48 : 32;
        const lines = [];

        lines.push(this.centerReceiptText(store.name || 'MavaPOS', width));
        [store.tagline, receiptOptions.show_store_address !== false ? store.address : '', store.instagram]
            .filter(Boolean)
            .forEach((line) => lines.push(this.centerReceiptText(line, width)));

        lines.push('');
        lines.push(this.receiptDivider(width, 'double'));
        lines.push(`No Nota: ${receipt.invoice_number || '-'}`);
        lines.push(`Tanggal : ${receipt.sold_at || '-'}`);

        if (receiptOptions.show_cashier !== false) {
            lines.push(`Kasir   : ${receipt.cashier || '-'}`);
        }

        lines.push(`Bayar   : ${this.paymentLabel(receipt.payment_method)}`);
        lines.push(this.receiptDivider(width, 'double'));

        (receipt.items || []).forEach((item) => {
            this.receiptLineItem(item, width).forEach((line) => lines.push(line));
        });

        lines.push(this.receiptDivider(width, 'double'));
        lines.push('');
        lines.push(this.centerReceiptText(this.receiptKeyValue('TOTAL', this.formatRupiah(receipt.total), width), width));
        lines.push('');
        lines.push(this.receiptKeyValue('Dibayar', this.formatRupiah(receipt.paid_amount), width));
        lines.push(this.receiptKeyValue('Kembali', this.formatRupiah(receipt.change_amount), width));
        lines.push('');
        this.wrapReceiptText(receiptOptions.footer_note || 'Terima kasih atas kunjungan Anda.', width)
            .forEach((line) => lines.push(this.centerReceiptText(line, width)));

        const encoder = new TextEncoder();
        const init = [0x1b, 0x40];
        const alignLeft = [0x1b, 0x61, 0x00];
        const feed = [0x0a, 0x0a, 0x0a];
        const cut = [0x1d, 0x56, 0x42, 0x00];
        const body = Array.from(encoder.encode(`${lines.join('\n')}\n`));

        return new Uint8Array([...init, ...alignLeft, ...body, ...feed, ...cut]);
    },

    async writeBluetoothChunks(characteristic, payload) {
        const chunkSize = 120;
        const chunkDelay = 50;

        for (let offset = 0; offset < payload.length; offset += chunkSize) {
            const chunk = payload.slice(offset, offset + chunkSize);

            if (typeof characteristic.writeValueWithoutResponse === 'function') {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValue(chunk);
            }

            await new Promise((resolve) => setTimeout(resolve, chunkDelay));
        }
    },

    async printIminReceipt(receipt) {
        const socket = await this.connectIminPrinter();
        const receiptOptions = receipt.receipt || {};
        const store = receipt.store || {};
        const paperCharacters = receiptOptions.paper_width === '80' ? 48 : 32;
        const storeName = store.name || 'MavaPOS';
        const storeTagline = store.tagline || '';
        const storeAddress = store.address || '';
        const storeInstagram = store.instagram || '';
        const footerNote = receiptOptions.footer_note || 'Terima kasih atas kunjungan Anda.';
        const textSize = this.receiptTextSize();

        try {
            this.sendIminCommand(socket, 'SPI', 1);
            await this.sleep(250);
            const status = await this.getIminPrinterStatus(socket);

            if (Number(status.value) !== 0) {
                throw new Error(`Status IMIN tidak normal: ${status.text}`);
            }

            const commands = [
                ['', 6, 1],
                ['', 7, 28],
                ['', 9, 1],
                [`${storeName}\n`, 12],
                ['', 7, 24],
                ['', 9, 0],
            ];

            [storeTagline, receiptOptions.show_store_address !== false ? storeAddress : '', storeInstagram]
                .filter(Boolean)
                .forEach((line) => commands.push([`${line}\n`, 12]));

            commands.push(
                ['\n', 12],
                [`${this.receiptDivider(paperCharacters, 'double')}\n`, 12],
                ['', 7, textSize],
                ['', 6, 0],
                [`No Nota: ${receipt.invoice_number || '-'}\n`, 12],
                [`Tanggal : ${receipt.sold_at || '-'}\n`, 12],
            );

            if (receiptOptions.show_cashier !== false) {
                commands.push([`Kasir   : ${receipt.cashier || '-'}\n`, 12]);
            }

            commands.push(
                [`Bayar   : ${this.paymentLabel(receipt.payment_method)}\n`, 12],
                [`${this.receiptDivider(paperCharacters, 'single')}\n`, 12],
            );

            (receipt.items || []).forEach((item) => {
                commands.push(this.iminColumnsCommand(
                    [item.name || '-', this.formatRupiah(item.line_total)],
                    [0.62, 0.38],
                    [0, 2],
                    textSize,
                ));
                commands.push([
                    `  ${Number(item.quantity || 0)} x ${this.formatRupiah(item.unit_price)}${item.sku ? ` · ${item.sku}` : ''}\n`,
                    12,
                ]);
            });

            commands.push(
                [`${this.receiptDivider(paperCharacters, 'single')}\n`, 12],
                this.iminColumnsCommand(['Subtotal', this.formatRupiah(receipt.subtotal)], [0.55, 0.45], [0, 2], textSize)
            );

            if (Number(receipt.discount || 0) > 0) {
                commands.push(
                    this.iminColumnsCommand(['Diskon', `-${this.formatRupiah(receipt.discount)}`], [0.55, 0.45], [0, 2], textSize)
                );
            }

            commands.push(
                [`${this.receiptDivider(paperCharacters, 'double')}\n`, 12],
                ['\n', 12],
                ['', 9, 1],
                ['', 6, 1],
                ['', 7, 32],
                this.iminColumnsCommand(['TOTAL', this.formatRupiah(receipt.total)], [0.55, 0.45], [0, 2], textSize),
                ['', 7, 24],
                ['', 6, 0],
                ['', 9, 0],
                ['\n', 12],
                this.iminColumnsCommand(['Dibayar', this.formatRupiah(receipt.paid_amount)], [0.55, 0.45], [0, 2], textSize),
                this.iminColumnsCommand(['Kembali', this.formatRupiah(receipt.change_amount)], [0.55, 0.45], [0, 2], textSize),
                ['\n', 12],
                ['', 9, 1],
                ['', 6, 1],
            );

            this.wrapReceiptText(footerNote, paperCharacters).forEach((line) => {
                commands.push([`${line}\n`, 12]);
            });

            commands.push(['', 9, 0], ['', 6, 0]);
            commands.push(['\n', 12], ['', 4, 80], ['', 5]);
            await this.sendIminCommands(socket, commands);
        } finally {
            setTimeout(() => socket.close(), 250);
        }
    },

    connectIminPrinter() {
        return new Promise((resolve, reject) => {
            const SocketClass = window.WebSocket || window.MozWebSocket;

            if (!SocketClass) {
                reject(new Error('Browser tidak mendukung WebSocket untuk IMIN InnerPrinter.'));
                return;
            }

            const socket = new SocketClass('ws://127.0.0.1:8081/websocket');
            const timeout = setTimeout(() => {
                socket.close();
                reject(new Error('Tidak bisa terhubung ke print service IMIN di 127.0.0.1:8081.'));
            }, 5000);

            socket.onopen = () => {
                clearTimeout(timeout);
                resolve(socket);
            };
            socket.onerror = () => {
                clearTimeout(timeout);
                reject(new Error('Print service IMIN tidak tersedia. Pastikan dibuka dari tablet IMIN.'));
            };
        });
    },

    getIminPrinterStatus(socket) {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                reject(new Error('Tidak ada balasan status dari IMIN InnerPrinter.'));
            }, 5000);

            socket.onmessage = (event) => {
                if (event.data === 'request') {
                    this.sendIminCommand(socket, 'ping');
                    return;
                }

                let data = null;

                try {
                    data = JSON.parse(event.data);
                } catch (error) {
                    return;
                }

                if (data?.data?.text === 'ping') {
                    this.sendIminCommand(socket, 'ping');
                    return;
                }

                if (data?.type === 2) {
                    clearTimeout(timeout);
                    resolve({
                        ...data.data,
                        text: this.iminPrinterStatusText(data.data?.value),
                    });
                }
            };

            this.sendIminCommand(socket, 'SPI', 2);
        });
    },

    async sendIminCommands(socket, commands) {
        for (const command of commands) {
            if (command?.columns) {
                this.sendIminColumns(socket, command.columns);
            } else {
                this.sendIminCommand(socket, ...command);
            }

            await this.sleep(60);
        }
    },

    sendIminColumns(socket, columns) {
        const width = columns.width || 576;
        const colWidthArr = columns.widths.map((ratio) => Math.round(width * ratio));

        this.sendIminCommand(socket, '', 14, width, {
            colTextArr: columns.texts,
            colWidthArr,
            colAlign: columns.align,
            size: columns.size,
        });
    },

    sendIminCommand(socket, text = '', type = 0, value = -1, extra = {}) {
        socket.send(JSON.stringify({
            data: {
                text,
                value,
                labelData: {},
                ...extra,
            },
            type,
        }));
    },

    iminColumnsCommand(texts, widths, align, size, width = 576) {
        return {
            columns: {
                texts,
                widths,
                align,
                size,
                width,
            },
        };
    },

    iminPrinterStatusText(value) {
        switch (String(value)) {
            case '0':
                return 'Normal';
            case '3':
                return 'Head printer terbuka';
            case '7':
                return 'Kertas habis';
            case '8':
                return 'Kertas hampir habis';
            case '99':
                return 'Printer error';
            default:
                return 'Printer tidak terhubung atau belum menyala';
        }
    },

    receiptDivider(length, style = 'single') {
        const chars = {
            single: '-',
            double: '=',
            dotted: '-',
        };

        return (chars[style] || chars.single).repeat(length);
    },

    receiptTextSize() {
        return 16;
    },

    receiptTypography() {
        return {
            body: 14,
            heading: 18,
            small: 12.5,
            item: 15,
            meta: 13,
            total: 16,
        };
    },

    receiptLineItem(item, length) {
        const total = this.formatRupiah(item.line_total);
        const quantity = Number(item.quantity || 0);
        const unitPrice = this.formatRupiah(item.unit_price);
        const meta = `  ${quantity} x ${unitPrice}`;
        const maxNameLength = Math.max(12, length - total.length - 1);
        const nameLines = this.wrapReceiptText(item.name || '-', maxNameLength);
        const firstName = nameLines.shift() || '-';
        const lines = [];

        if ((firstName.length + total.length + 1) <= length) {
            lines.push(this.receiptKeyValue(firstName, total, length));
        } else {
            lines.push(firstName);
            lines.push(this.rightAlignReceiptText(total, length));
        }

        nameLines.forEach((line) => lines.push(line));
        lines.push(meta.length <= length ? meta : this.wrapReceiptText(meta.trim(), length).join('\n'));

        return lines.flatMap((line) => String(line).split('\n'));
    },

    receiptKeyValue(key, value, length) {
        const left = String(key || '');
        const right = String(value || '');
        const spaceLength = Math.max(1, length - left.length - right.length);

        return `${left}${' '.repeat(spaceLength)}${right}`;
    },

    rightAlignReceiptText(text, length) {
        const value = String(text || '');

        return `${' '.repeat(Math.max(0, length - value.length))}${value}`;
    },

    centerReceiptText(text, length) {
        const value = String(text || '');
        const left = Math.max(0, Math.floor((length - value.length) / 2));

        return `${' '.repeat(left)}${value}`;
    },

    wrapReceiptText(text, length) {
        const words = String(text || '').split(/\s+/).filter(Boolean);
        const lines = [];
        let line = '';

        words.forEach((word) => {
            if (!line) {
                line = word;
                return;
            }

            if ((line.length + word.length + 1) <= length) {
                line = `${line} ${word}`;
                return;
            }

            lines.push(line);
            line = word;
        });

        if (line) {
            lines.push(line);
        }

        return lines.length ? lines : [''];
    },

    sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    },

    effectivePrintPreference(key, fallback = false) {
        return typeof this.printPreferences[key] === 'boolean'
            ? this.printPreferences[key]
            : Boolean(fallback);
    },

    syncPrintPreferencesFromReceipt(receipt) {
        const printer = receipt?.printer || {};

        if (typeof this.printPreferences.autoPrint !== 'boolean') {
            this.printPreferences.autoPrint = Boolean(printer.auto_print);
        }

        if (typeof this.printPreferences.closeAfterPrint !== 'boolean') {
            this.printPreferences.closeAfterPrint = Boolean(printer.close_after_print);
        }
    },

    payExact() {
        this.paymentMethod = 'cash';
        this.paidAmount = String(this.total);
    },

    async startShift() {
        this.shiftError = '';
        this.shiftLoading = true;

        try {
            const response = await fetch(this.endpoints.startShift, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    opening_cash_amount: this.numberFromInput(this.openingCashAmount),
                    opening_note: this.openingNote,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.shiftError = payload.message || 'Shift kasir gagal dimulai.';
                notify(this.shiftError, 'error');
                return;
            }

            this.shift = payload.shift;
            this.blockingShift = null;
            this.startModal = false;
            this.sopModal = true;
            this.openingCashAmount = '';
            this.openingNote = '';
            notify(payload.message || 'Shift kasir dimulai.');
        } finally {
            this.shiftLoading = false;
        }
    },

    async closeShift() {
        this.shiftError = '';
        this.shiftLoading = true;

        try {
            const response = await fetch(this.endpoints.closeShift, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    closing_note: this.closingNote,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.shiftError = payload.message || 'Shift kasir gagal ditutup.';
                notify(this.shiftError, 'error');
                return;
            }

            this.shift = null;
            this.closeModal = false;
            this.startModal = true;
            this.sopModal = false;
            this.closingNote = '';
            this.clearCart();
            notify(payload.message || 'Shift kasir ditutup.');
        } finally {
            this.shiftLoading = false;
        }
    },

    async checkout() {
        if (!this.canCheckout) {
            notify('Lengkapi pembayaran sebelum checkout.', 'error');
            return;
        }

        this.checkoutError = '';
        this.checkoutLoading = true;

        try {
            const response = await fetch(this.endpoints.checkout, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    items: this.cart.map((item) => ({
                        id: item.id,
                        quantity: item.quantity,
                    })),
                    payment_method: this.paymentMethod,
                    discount: this.discountValue,
                    paid_amount: this.paymentMethod === 'cash' ? this.paid : this.total,
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.checkoutError = payload.message || 'Transaksi gagal diselesaikan.';
                notify(this.checkoutError, 'error');
                return;
            }

            this.items = payload.items || this.items;
            this.shift = payload.shift || this.shift;
            this.lastReceipt = payload.sale || null;
            this.syncPrintPreferencesFromReceipt(this.lastReceipt);
            this.clearCart();
            this.receiptModal = Boolean(this.lastReceipt);
            if (this.lastReceipt && this.effectivePrintPreference('autoPrint', this.lastReceipt.printer?.auto_print)) {
                setTimeout(() => this.printReceipt(), 150);
            }
            notify(payload.message || 'Transaksi berhasil diselesaikan.');
        } finally {
            this.checkoutLoading = false;
        }
    },
}));

Alpine.data('productRecipeManager', (initialProducts = [], rawMaterials = []) => ({
    products: initialProducts,
    rawMaterials,
    modalOpen: false,
    selectedProductId: '',
    items: [],

    emptyItem() {
        return {
            raw_material_id: '',
            quantity: '',
            unit: '',
        };
    },

    openModal(productId = null) {
        this.modalOpen = true;
        this.selectedProductId = productId ? String(productId) : '';
        this.loadSelectedRecipe();
    },

    closeModal() {
        this.modalOpen = false;
        this.selectedProductId = '';
        this.items = [this.emptyItem()];
    },

    selectedProduct() {
        return this.products.find((product) => String(product.id) === String(this.selectedProductId));
    },

    loadSelectedRecipe() {
        const product = this.selectedProduct();

        if (!product || !Array.isArray(product.items) || product.items.length === 0) {
            this.items = [this.emptyItem()];
            return;
        }

        this.items = product.items.map((item) => ({
            raw_material_id: item.raw_material_id ? String(item.raw_material_id) : '',
            quantity: item.quantity || '',
            unit: item.unit || this.rawMaterialUnit(item.raw_material_id),
        }));
    },

    addItem() {
        this.items.push(this.emptyItem());
    },

    removeItem(index) {
        if (this.items.length === 1) {
            return;
        }

        this.items.splice(index, 1);
    },

    rawMaterialUnit(rawMaterialId) {
        return this.rawMaterials.find((material) => String(material.id) === String(rawMaterialId))?.unit || '';
    },

    syncItemUnit(item) {
        item.unit = this.rawMaterialUnit(item.raw_material_id);
    },
}));

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    const hasChart = [
        '#chartOne',
        '#chartTwo',
        '#chartThree',
        '#chartSix',
        '#chartEight',
        '#chartThirteen',
    ].some((selector) => document.querySelector(selector));

    // Chart imports
    const chartReady = hasChart ? window.loadApexCharts() : Promise.resolve();
    if (document.querySelector('#chartOne')) {
        chartReady.then(() => import('./components/chart/chart-1')).then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        chartReady.then(() => import('./components/chart/chart-2')).then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        chartReady.then(() => import('./components/chart/chart-3')).then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        chartReady.then(() => import('./components/chart/chart-6')).then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        chartReady.then(() => import('./components/chart/chart-8')).then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        chartReady.then(() => import('./components/chart/chart-13')).then(module => module.initChartThirteen());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});
