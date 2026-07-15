# Transaction Void, Cashier Stock-In, and Shift Recap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add audited owner/admin transaction voiding with inventory reversal, a focused cashier stock-in flow, and printable shift recaps after normal and forced close.

**Architecture:** `TransactionVoidService` owns the atomic sale reversal, `InventoryStockMovementService` owns branch-locked product/variant stock mutations, and `CashierShiftSummaryService` owns active-sale aggregates plus the shared recap payload. Blade/Alpine surfaces call focused endpoints while shared JavaScript recap builders handle browser, Bluetooth, and IMIN printing without a drawer command.

**Tech Stack:** Laravel 12, PHP 8.2+, Eloquent, Pest, Blade, Alpine.js, Tailwind CSS, Vite, ESC/POS, IMIN InnerPrinter WebSocket.

## Global Constraints

- Only `owner` and `admin` may void transactions.
- Voided transactions remain stored and auditable; they are excluded from active financial totals.
- Void reverses branch product/variant stock and recipe raw-material usage exactly once.
- Cashiers may only add product/variant stock in the active branch and receive no broader inventory permissions.
- Normal close and forced close expose the same recap fields and all three configured printer modes.
- Shift recap printing must not emit a cash-drawer command.
- Preserve the existing Blade, Alpine, Tailwind, dark-mode, route, and printer patterns.

---

### Task 1: Create shared inventory movement and cashier stock-in backend

**Files:**
- Create: `app/Services/InventoryStockMovementService.php`
- Create: `app/Http/Controllers/CashierStockInController.php`
- Create: `database/migrations/2026_07_15_000001_add_created_by_user_id_to_stock_movements_table.php`
- Modify: `app/Models/StockMovement.php`
- Modify: `app/Http/Controllers/InventoryController.php`
- Modify: `app/Helpers/MenuHelper.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CashierStockInTest.php`

**Interfaces:**
- Produces: `InventoryStockMovementService::record(string $sku, string $type, int $quantity, int $branchId, ?int $actorId, ?string $reference, ?string $note, CarbonInterface $occurredAt): array` returning `item`, `movement`, `stockBefore`, and `stockAfter`.
- Produces: routes `cashier-stock-in.index` and `cashier-stock-in.store`.

- [ ] **Step 1: Write failing permission, branch-isolation, product, variant, validation, actor-audit, and rendered-page tests**

```php
test('cashier stock in updates only the active branch and records the actor', function () {
    $cashier = User::factory()->create(['role' => 'kasir']);
    $branch = Branch::query()->firstOrFail();
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $inventory = app(BranchInventoryManager::class)->forProduct($branch->id, $product);
    $before = $inventory->stock;

    $this->actingAs($cashier)->postJson(route('cashier-stock-in.store'), [
        'sku' => $product->sku,
        'quantity' => 4,
        'reference' => 'NOTA-1',
    ])->assertCreated()->assertJsonPath('stockBefore', $before)->assertJsonPath('stockAfter', $before + 4);

    $this->assertDatabaseHas('stock_movements', [
        'branch_id' => $branch->id,
        'created_by_user_id' => $cashier->id,
        'reference' => 'NOTA-1',
        'type' => 'in',
    ]);
});
```

- [ ] **Step 2: Run the focused tests and confirm RED**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php`

Expected: FAIL because the named route, controller, migration field, service, and page do not exist.

- [ ] **Step 3: Implement the migration, service, focused controller, routes, menu entry, and InventoryController delegation**

```php
Route::middleware('role:kasir')->group(function () {
    Route::get('/stock-in', [CashierStockInController::class, 'index'])->name('cashier-stock-in.index');
    Route::post('/stock-in', [CashierStockInController::class, 'store'])->name('cashier-stock-in.store');
});
```

The service resolves product or variant SKU, locks the active branch inventory row in a database transaction, rejects invalid stock-out, synchronizes the compatibility stock field, and writes `StockMovement` with `branch_id` and `created_by_user_id`.

- [ ] **Step 4: Run focused tests and confirm GREEN**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php`

Expected: PASS for access, branch isolation, actor audit, product/variant stock-in, validation, and forbidden full Inventory behavior.

### Task 2: Build the focused cashier stock-in page

**Files:**
- Create: `resources/views/pages/inventory/cashier-stock-in.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/CashierStockInTest.php`

**Interfaces:**
- Consumes: item payload `{sku,name,category,stock,isVariant}` and route `cashier-stock-in.store`.
- Produces: Alpine component `cashierStockInManager(initialItems, endpoint)`.

- [ ] **Step 1: Add a failing rendered-contract test**

```php
$this->actingAs($cashier)->get(route('cashier-stock-in.index'))
    ->assertOk()
    ->assertSee('Stok Masuk')
    ->assertSee('cashierStockInManager', false)
    ->assertSee('Tambah Stok');
```

- [ ] **Step 2: Run the rendered test and confirm RED**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php --filter='focused stock in'`

Expected: FAIL because the page and Alpine contract are missing.

- [ ] **Step 3: Implement search, selection, quantity, optional note/reference disclosure, submit states, and stock delta feedback**

```js
Alpine.data('cashierStockInManager', (initialItems = [], endpoint = '') => ({
    items: initialItems, query: '', selected: null, quantity: '', reference: '', note: '',
    loading: false, error: '', result: null,
    async submit() { /* POST JSON, update selected/local stock, preserve input on failure */ },
}));
```

Use existing card, form, empty-state, dark-mode, focus-ring, and touch-target patterns. A successful request displays `stok awal → stok baru`, clears the quantity, and returns focus to search.

- [ ] **Step 4: Run the focused tests and build**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php && npm run build`

Expected: tests PASS and Vite exits 0.

### Task 3: Add sale void audit schema and raw-material usage snapshots

**Files:**
- Create: `database/migrations/2026_07_15_000002_add_void_fields_to_pos_sales_table.php`
- Create: `database/migrations/2026_07_15_000003_create_pos_sale_raw_material_usages_table.php`
- Create: `app/Models/PosSaleRawMaterialUsage.php`
- Modify: `app/Models/PosSale.php`
- Modify: `app/Http/Controllers/PosController.php`
- Test: `tests/Feature/TransactionVoidTest.php`

**Interfaces:**
- Produces: `PosSale::scopeActive(Builder $query): Builder`.
- Produces: `PosSale::rawMaterialUsages(): HasMany` and usage snapshots with `raw_material_id`, `quantity`, `unit`, and `is_legacy_fallback`.

- [ ] **Step 1: Write failing schema/cast/snapshot tests**

```php
test('checkout snapshots consumed raw material quantities', function () {
    // Create recipe, start shift, checkout two products.
    $this->assertDatabaseHas('pos_sale_raw_material_usages', [
        'raw_material_id' => $material->id,
        'quantity' => '0.500',
        'is_legacy_fallback' => false,
    ]);
});
```

- [ ] **Step 2: Run the test and confirm RED**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php --filter='snapshots'`

Expected: FAIL because the table and relationship do not exist.

- [ ] **Step 3: Add schema, model relationships/casts, active scope, and snapshot creation during checkout**

```php
public function scopeActive(Builder $query): Builder
{
    return $query->whereNull('voided_at');
}
```

Aggregate duplicate recipe use per sale/material before persisting one snapshot row. Preserve the existing raw-material decrement behavior.

- [ ] **Step 4: Run the snapshot test and confirm GREEN**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php --filter='snapshots'`

Expected: PASS.

### Task 4: Implement atomic owner/admin transaction voiding and reporting exclusions

**Files:**
- Create: `app/Services/TransactionVoidService.php`
- Modify: `app/Http/Controllers/SaleController.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `app/Http/Controllers/GlobalSearchController.php`
- Modify: `app/Services/ActivityNotificationService.php`
- Modify: `app/Http/Controllers/PosController.php`
- Modify: `app/Http/Controllers/CashierShiftController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TransactionVoidTest.php`

**Interfaces:**
- Consumes: `InventoryStockMovementService::record(...)`.
- Produces: `TransactionVoidService::void(PosSale $sale, int $branchId, User $actor, string $reason): PosSale`.
- Produces: route `sales.void` under `role:owner,admin`.

- [ ] **Step 1: Write failing tests for role, branch, required reason, audit retention, product/variant/raw-material restoration, idempotency, shift recalculation, and report exclusion**

```php
$this->actingAs($owner)->postJson(route('sales.void', $sale), ['reason' => 'Duplikat input'])
    ->assertOk()->assertJsonPath('sale.status', 'voided');

$this->assertDatabaseHas('pos_sales', [
    'id' => $sale->id,
    'voided_by_user_id' => $owner->id,
    'void_reason' => 'Duplikat input',
]);
```

- [ ] **Step 2: Run the void suite and confirm RED**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php`

Expected: FAIL because the route/service and active-sale exclusions do not exist.

- [ ] **Step 3: Implement route/controller/service and apply `active()` to financial queries**

Inside one transaction: lock sale; verify active branch; reject `voided_at`; restore product/variant stock; restore snapshot raw materials or create explicitly marked legacy fallback usages from the current recipe; write reversal movements; set void audit fields; recalculate the related shift from active sales.

Apply `active()` to dashboard, reports/downloads, global active search/notifications, POS shift aggregates, and cashier shift aggregates. Keep Data Penjualan's row query inclusive while cloning an `active()` query for summary cards.

- [ ] **Step 4: Run focused tests and confirm GREEN**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php tests/Feature/PosCashierShiftTest.php`

Expected: PASS with exact-once restoration and voided-sale exclusions.

### Task 5: Add the Data Penjualan void interaction

**Files:**
- Modify: `resources/views/pages/sales/index.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/TransactionVoidTest.php`

**Interfaces:**
- Consumes: `sales.void` JSON `{message,sale,summary}`.
- Produces: Alpine component `salesVoidManager(endpointTemplate)`.

- [ ] **Step 1: Add failing rendered authorization and modal-contract tests**

```php
$this->actingAs($owner)->get(route('sales'))->assertSee('Batalkan Transaksi');
$this->actingAs($cashier)->get(route('sales'))->assertDontSee('Batalkan Transaksi');
```

- [ ] **Step 2: Run tests and confirm RED**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php --filter='void action'`

Expected: FAIL because the action and dialog are absent.

- [ ] **Step 3: Implement owner/admin-only action, required-reason confirmation dialog, loading/error states, and void status badge**

The page reloads after a successful void so server-rendered pagination, row state, and summary cards remain authoritative. Voided rows show actor, time, and reason and never show the action again.

- [ ] **Step 4: Run rendered tests and build**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php && npm run build`

Expected: PASS and build exit 0.

### Task 6: Centralize shift totals and shared recap payload

**Files:**
- Create: `app/Services/CashierShiftSummaryService.php`
- Modify: `app/Http/Controllers/PosController.php`
- Modify: `app/Http/Controllers/CashierShiftController.php`
- Modify: `app/Models/CashierShift.php`
- Test: `tests/Feature/PosCashierShiftTest.php`

**Interfaces:**
- Produces: `CashierShiftSummaryService::refresh(CashierShift $shift): CashierShift`.
- Produces: `CashierShiftSummaryService::recap(CashierShift $shift): array` with store, receipt, printer, shift, payment, and expected-cash fields.

- [ ] **Step 1: Write failing tests that normal and forced close expose identical recap fields**

```php
$response->assertJsonPath('recap.expectedCashInDrawer', 98000)
    ->assertJsonPath('recap.cashTotal', 23000)
    ->assertJsonPath('recap.openingCashAmount', 75000);
```

Forced close requests with `Accept: application/json` must return the same `recap` contract; regular form submissions keep redirect compatibility and flash the recap.

- [ ] **Step 2: Run shift tests and confirm RED**

Run: `php artisan test --compact tests/Feature/PosCashierShiftTest.php --filter='recap'`

Expected: FAIL because no recap payload/service exists.

- [ ] **Step 3: Extract active-sale aggregate refresh and build the shared payload**

Replace duplicated aggregate methods in both controllers and `TransactionVoidService` with the summary service. Include localized timestamps, closing note, store identity, receipt options, and printer settings.

- [ ] **Step 4: Run shift tests and confirm GREEN**

Run: `php artisan test --compact tests/Feature/PosCashierShiftTest.php`

Expected: PASS for existing close behavior, forced-close authorization, active-sale totals, and recap contract.

### Task 7: Build recap popups and shared three-mode printing

**Files:**
- Modify: `resources/views/pages/pos/index.blade.php`
- Modify: `resources/views/pages/cashier-shifts/index.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/PosCashierShiftTest.php`

**Interfaces:**
- Consumes: shared recap payload.
- Produces: `shiftRecapPrinter(recap)` helpers for browser, Bluetooth ESC/POS, and IMIN; no drawer opcode.
- Produces: Alpine recap state in `posManager` and `cashierShiftManager`.

- [ ] **Step 1: Add failing rendered tests for both popup entry points and print controls**

```php
$this->actingAs($cashier)->get(route('pos'))->assertSee('Print Rekap');
$this->actingAs($owner)->get(route('cashier-shifts'))->assertSee('cashierShiftManager', false);
```

- [ ] **Step 2: Run the rendered tests and confirm RED**

Run: `php artisan test --compact tests/Feature/PosCashierShiftTest.php --filter='rekap'`

Expected: FAIL because popup contracts are missing.

- [ ] **Step 3: Implement shared recap layout and printer functions**

Normal close assigns `payload.recap`, hides the close dialog, and delays the new-shift modal until `Lewati`. Forced close uses JSON fetch, opens the same recap layout, and refreshes after dismissal. Print errors stay in the popup with `Coba Print Lagi` and `Lewati`.

The print builders output transaction count, gross sales, discount, net sales, cash, QRIS, card, opening cash, and expected cash. Do not reuse the receipt drawer command.

- [ ] **Step 4: Run focused tests and build**

Run: `php artisan test --compact tests/Feature/PosCashierShiftTest.php && npm run build`

Expected: PASS and build exit 0.

### Task 8: Full regression and visual verification

**Files:**
- Modify only if verification reveals an in-scope defect.

**Interfaces:**
- Consumes all prior task outputs.
- Produces verified release-ready behavior.

- [ ] **Step 1: Format changed PHP files**

Run: `vendor/bin/pint --dirty`

Expected: exit 0.

- [ ] **Step 2: Run focused backend verification**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php tests/Feature/TransactionVoidTest.php tests/Feature/PosCashierShiftTest.php`

Expected: all focused tests PASS.

- [ ] **Step 3: Run the full suite and frontend build**

Run: `composer run test && npm run build`

Expected: both commands exit 0 with no failures.

- [ ] **Step 4: Check patch integrity**

Run: `git diff --check && git status --short`

Expected: no whitespace errors; only scoped plan, migrations, models, services, controllers, routes, views, JavaScript, and tests are changed.

- [ ] **Step 5: Render representative flows**

Verify Data Penjualan void, cashier Stok Masuk, POS normal close recap, and Shift Kasir forced-close recap at mobile, tablet, and desktop widths. Confirm no horizontal document overflow, clipped controls, console errors, duplicate submissions, or cash-drawer commands during recap print.
