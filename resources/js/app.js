import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';



window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;

Alpine.data('productManager', (initialProducts = [], initialCategories = []) => ({
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
        search: '',
        category: '',
        status: '',
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
                this.normalize(product.category) === this.filters.category;
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
            return;
        }

        const payload = await response.json();
        this.products.unshift(payload.product);
        this.currentPage = 1;
        this.closeCreateModal();
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
            return;
        }

        const payload = await response.json();
        const productIndex = this.products.findIndex((product) => product.sku === this.editingProductSku);

        if (productIndex !== -1) {
            this.products.splice(productIndex, 1, payload.product);
        }

        this.closeEditModal();
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

Alpine.data('productCategoryManager', (initialCategories = []) => ({
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
        search: '',
        status: '',
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
            return;
        }

        const payload = await response.json();
        this.categories.unshift(payload.category);
        this.currentPage = 1;
        this.closeCreateModal();
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
            return;
        }

        const payload = await response.json();
        const categoryIndex = this.categories.findIndex((category) => category.code === this.editingCategoryCode);

        if (categoryIndex !== -1) {
            this.categories.splice(categoryIndex, 1, payload.category);
        }

        this.closeEditModal();
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

Alpine.data('contactManager', (initialItems = [], routePath = '', entityLabel = '', entityName = '', entityPlural = '') => ({
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
        search: '',
        status: '',
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
            return;
        }

        const payload = await response.json();
        this.items.unshift(payload.item);
        this.currentPage = 1;
        this.closeCreateModal();
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
            return;
        }

        const payload = await response.json();
        const itemIndex = this.items.findIndex((item) => item.code === this.editingCode);

        if (itemIndex !== -1) {
            this.items.splice(itemIndex, 1, payload.item);
        }

        this.closeEditModal();
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

Alpine.data('inventoryManager', (initialItems = [], initialMovements = []) => ({
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
        search: '',
        status: '',
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
            return;
        }

        const payload = await response.json();
        const itemIndex = this.items.findIndex((item) => item.sku === this.editingSku);

        if (itemIndex !== -1) {
            this.items.splice(itemIndex, 1, payload.item);
        }

        this.closeEditModal();
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

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});
