# Global Tablet UI and Cashier Stock-In Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill tablet viewports across authenticated MavaPOS pages and give cashiers a focused, audited stock-in flow without broader inventory permissions.

**Architecture:** A dedicated `CashierStockInController` and page expose only product selection plus positive quantity, while an `InventoryStockMovementService` owns branch-locked inventory mutations used by both cashier and full Inventory flows. Responsive changes live in the shared application shell and Tailwind entry stylesheet, with page-level breakpoint changes only where dashboard charts, POS grids, or dense inventory controls require explicit layout decisions.

**Tech Stack:** Laravel 12, Blade, Alpine.js 3, Tailwind CSS 4, Vite 7, Pest/PHPUnit, SQLite test database.

## Global Constraints

- Preserve Public Sans, TailAdmin colour tokens, dark mode, existing routes, and the application sidebar.
- Use `public/logo.png` for every runtime brand mark; preserve semantic menu and action icons.
- Mobile is below `768px`; tablet is `768px` through `1279px`; desktop is `1280px` and wider.
- The sidebar remains an overlay drawer below `1280px`.
- Touch-reachable controls have a minimum `44px` hit target on tablet/coarse pointers.
- Cashiers may add stock only for the active branch and may not stock out, correct absolute stock, change purchase price, or open full Inventory.
- Required stock-in inputs are only product or variant plus positive integer quantity; reference and note are optional.
- Preserve `overflow-x: clip` on `html` and `body`; wide tables scroll inside their own container.
- Keep motion functional and respect `prefers-reduced-motion`.

---

### Task 1: Add actor-audited stock movement service

**Files:**
- Create: `database/migrations/2026_07_14_000001_add_created_by_user_id_to_stock_movements_table.php`
- Create: `app/Services/InventoryStockMovementService.php`
- Modify: `app/Models/StockMovement.php`
- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/InventoryController.php`
- Test: `tests/Feature/CashierStockInTest.php`

**Interfaces:**
- Consumes: `BranchInventoryManager::forProduct(int, Product, bool)` and `forVariant(int, ProductVariant, bool)`.
- Produces: `InventoryStockMovementService::record(string $sku, string $type, int $quantity, int $branchId, ?int $actorId, ?string $reference, ?string $note, mixed $occurredAt): array{product: Product, movement: StockMovement}`.

- [ ] **Step 1: Write the failing actor/service test**

```php
test('stock movement records the authenticated actor and active branch', function () {
    $cashier = User::factory()->create(['role' => 'kasir', 'trial_ends_at' => now()->addDay()]);
    $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
    $branch = app(BranchContext::class)->active();
    $before = app(BranchInventoryManager::class)->stockForProduct($branch->id, $product);

    $result = app(InventoryStockMovementService::class)->record(
        $product->sku, 'in', 6, $branch->id, $cashier->id, 'NOTA-1', 'Barang datang', now(),
    );

    expect($result['movement']->created_by_user_id)->toBe($cashier->id)
        ->and($result['movement']->stock_before)->toBe($before)
        ->and($result['movement']->stock_after)->toBe($before + 6);
});
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php`

Expected: FAIL because `InventoryStockMovementService` and `created_by_user_id` do not exist.

- [ ] **Step 3: Implement the migration, relationships, and service**

```php
Schema::table('stock_movements', function (Blueprint $table): void {
    $table->foreignId('created_by_user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
});
```

The service validates `in|out`, resolves product or variant SKU using the current fallback convention, starts a database transaction, locks the branch inventory row, rejects stock-out beyond availability, updates branch plus compatibility stock columns, and creates the complete `StockMovement` including actor.

- [ ] **Step 4: Route existing Inventory movements through the service**

Inject `InventoryStockMovementService` into `storeIn`, `storeOut`, and the internal movement handler; retain the existing JSON response shape by passing the returned models through `payload()` and `movementPayload()`.

- [ ] **Step 5: Run focused and existing inventory tests**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php tests/Feature/ExampleTest.php --filter='stock|inventory'`

Expected: PASS, with the actor test and existing Inventory movement behavior green.

### Task 2: Add the focused cashier stock-in endpoint and permissions

**Files:**
- Create: `app/Http/Controllers/CashierStockInController.php`
- Modify: `routes/web.php`
- Modify: `app/Helpers/MenuHelper.php`
- Test: `tests/Feature/CashierStockInTest.php`

**Interfaces:**
- Consumes: `InventoryStockMovementService::record(...)` from Task 1 and `BranchContext::activeId()`.
- Produces: named routes `cashier-stock-in.index` and `cashier-stock-in.store`, plus flat item payloads `{sku,name,category,stock,isVariant}`.

- [ ] **Step 1: Write failing permission and branch tests**

```php
test('cashier sees focused stock in but cannot manage full inventory', function () {
    $cashier = User::factory()->create(['role' => 'kasir', 'trial_ends_at' => now()->addDay()]);

    $this->actingAs($cashier)->get(route('cashier-stock-in.index'))
        ->assertOk()->assertSee('Stok Masuk')->assertSee('Tambah Stok');
    $this->actingAs($cashier)->get(route('inventory'))->assertForbidden();
    $this->actingAs($cashier)->postJson(route('inventory.out', 'SKU-001'), ['quantity' => 1])->assertForbidden();
});

test('cashier stock in updates only the active branch', function () {
    // Create two branches, switch to the second, submit quantity 4, and assert only the second inventory changes.
});
```

- [ ] **Step 2: Run the tests and verify RED**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php`

Expected: FAIL because the named routes and page do not exist.

- [ ] **Step 3: Add cashier-only routes and menu**

```php
Route::middleware('role:kasir')->group(function () {
    Route::get('/stock-in', [CashierStockInController::class, 'index'])->name('cashier-stock-in.index');
    Route::post('/stock-in', [CashierStockInController::class, 'store'])->name('cashier-stock-in.store');
});
```

Add `Stok Masuk` under Operasional with `roles => ['kasir']`; keep the existing full `Stok` menu limited to owner/admin/gudang.

- [ ] **Step 4: Implement controller validation and response**

```php
$validated = $request->validate([
    'sku' => ['required', 'string', 'max:100'],
    'quantity' => ['required', 'integer', 'min:1'],
    'reference' => ['nullable', 'string', 'max:100'],
    'note' => ['nullable', 'string', 'max:500'],
]);
```

Return HTTP 201 JSON containing `message`, refreshed `item`, and movement fields including `stockBefore` and `stockAfter`.

- [ ] **Step 5: Run the focused test file**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php`

Expected: PASS for route access, forbidden full Inventory, branch isolation, actor audit, validation, and variant stock-in.

### Task 3: Build the cashier stock-in interface with complete states

**Files:**
- Create: `resources/views/pages/inventory/cashier-stock-in.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/CashierStockInTest.php`

**Interfaces:**
- Consumes: controller item array and route endpoint.
- Produces: Alpine component `cashierStockInManager(initialItems, endpoint)`.

- [ ] **Step 1: Add failing markup assertions**

```php
$this->actingAs($cashier)->get(route('cashier-stock-in.index'))
    ->assertSee('x-data="cashierStockInManager', false)
    ->assertSee('Cari nama produk, SKU, atau barcode')
    ->assertSee('Tambahkan catatan')
    ->assertDontSee('Harga Beli')
    ->assertDontSee('Stok Keluar');
```

- [ ] **Step 2: Run the markup test and verify RED**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php --filter='focused stock in'`

Expected: FAIL because the page is missing the focused form.

- [ ] **Step 3: Build the Blade page**

Render a two-pane tablet layout: searchable item catalogue on the left and selected-item quantity form on the right, collapsing to one column on mobile. The form has visible labels, a `44px` minimum control height, optional notes in a disclosure, stable inline error text, and `aria-live="polite"` result feedback.

- [ ] **Step 4: Implement Alpine interaction states**

`cashierStockInManager` provides filtered items, selected item, quantity, optional fields, loading, error, success, and `submit()`; successful submit updates stock in the local catalogue, shows `stok awal → stok baru`, clears quantity, and focuses search for the next item.

- [ ] **Step 5: Re-run focused tests and frontend build**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php && npm run build`

Expected: PASS and Vite exits 0.

### Task 4: Add the global tablet responsive foundation

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `tokens.css` (Hallmark metadata export; ignored by Git)
- Test: `tests/Feature/TabletUiTest.php`

**Interfaces:**
- Produces: shell hooks `data-app-shell` and `data-app-content`, tablet tokens for control height, content gutter, card padding, and section gap.

- [ ] **Step 1: Write failing shell/CSS contract tests**

```php
test('authenticated shell exposes tablet responsive hooks', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)->toContain('data-app-content')
        ->and($css)->toContain('@media (min-width: 48rem) and (max-width: 79.999rem)')
        ->and($css)->toContain('--tablet-control-height: 2.75rem')
        ->and($css)->toContain('prefers-reduced-motion: reduce');
});
```

- [ ] **Step 2: Run the contract test and verify RED**

Run: `php artisan test --compact tests/Feature/TabletUiTest.php`

Expected: FAIL because the hooks and tablet media block do not exist.

- [ ] **Step 3: Add tablet tokens and shared rules**

At `48rem–79.999rem`, increase content gutters fluidly, raise form/button/icon-action hit areas to `2.75rem`, keep labels single-line, and let action rows wrap. Scope selectors beneath `[data-app-content]` so the landing and printer output are untouched. Add reduced-motion and fine-pointer hover guards without `transition-all`.

- [ ] **Step 4: Add shell data hooks and preserve sidebar behavior**

Mark only the authenticated app shell/content wrapper. Do not move the persistent sidebar breakpoint below `xl`.

- [ ] **Step 5: Run contract test and build**

Run: `php artisan test --compact tests/Feature/TabletUiTest.php && npm run build`

Expected: PASS and no Tailwind compilation error.

### Task 5: Tune data-heavy pages for tablet fill

**Files:**
- Modify: `resources/views/pages/dashboard/ecommerce.blade.php`
- Modify: `resources/views/components/ecommerce/ecommerce-metrics.blade.php`
- Modify: `resources/views/components/ecommerce/monthly-sale.blade.php`
- Modify: `resources/views/components/ecommerce/statistics-chart.blade.php`
- Modify: `resources/views/pages/pos/index.blade.php`
- Modify: `resources/views/pages/inventory/index.blade.php`
- Test: `tests/Feature/TabletUiTest.php`

**Interfaces:**
- Consumes: shared tablet rules from Task 4.
- Produces: dashboard two/four-column breakpoints, non-overflowing charts, fuller POS product grids, and touch-safe Inventory actions.

- [ ] **Step 1: Write failing responsive-markup assertions**

Assert dashboard chart grid uses `lg:grid-cols-2`, metrics use `lg:grid-cols-4`, chart containers use `min-w-0`, POS uses `md:grid-cols-3 lg:grid-cols-4`, and Inventory action controls carry touch-safe dimensions.

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test --compact tests/Feature/TabletUiTest.php`

Expected: FAIL against current `xl`-only dashboard charts and fixed chart minimum widths.

- [ ] **Step 3: Apply the smallest page-level breakpoint edits**

Move dashboard and POS grid expansion earlier where content remains readable, remove tablet chart minimum-width overflow, enlarge Inventory action targets at tablet, and keep table scroll containers intact.

- [ ] **Step 4: Run focused tests and build**

Run: `php artisan test --compact tests/Feature/CashierStockInTest.php tests/Feature/TabletUiTest.php && npm run build`

Expected: PASS.

### Task 6: Replace remaining TailAdmin brand marks with MAVAPOS

**Files:**
- Modify: `resources/views/layouts/app-header.blade.php`
- Modify: `resources/views/pages/auth/signin.blade.php`
- Modify: `resources/views/pages/auth/signup.blade.php`
- Test: `tests/Feature/TabletUiTest.php`

**Interfaces:**
- Consumes: `public/logo.png` through Laravel's `asset('logo.png')` helper.
- Produces: one runtime brand mark across mobile header and authentication surfaces.

- [ ] **Step 1: Write failing branding assertions**

```php
test('runtime branding uses the mavapos logo instead of tailadmin marks', function () {
    $views = collect([
        resource_path('views/layouts/app-header.blade.php'),
        resource_path('views/pages/auth/signin.blade.php'),
        resource_path('views/pages/auth/signup.blade.php'),
    ])->map(fn (string $path): string => file_get_contents($path))->implode("\n");

    expect($views)->not->toContain('/images/logo/logo.svg')
        ->not->toContain('/images/logo/logo-dark.svg')
        ->not->toContain('>mP<')
        ->and($views)->toContain("asset('logo.png')");
});
```

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test --compact tests/Feature/TabletUiTest.php --filter='runtime branding'`

Expected: FAIL because the mobile header still references TailAdmin SVG logos and auth still renders the `mP` badge.

- [ ] **Step 3: Replace runtime brand markup**

Use `<img src="{{ asset('logo.png') }}" alt="MAVAPOS" class="h-8 w-auto object-contain">` in the mobile header and appropriately sized `object-contain` instances in sign-in/sign-up brand panels. Do not replace functional SVG controls or delete legacy public assets.

- [ ] **Step 4: Run branding test and frontend build**

Run: `php artisan test --compact tests/Feature/TabletUiTest.php --filter='runtime branding' && npm run build`

Expected: PASS and Vite exits 0.

### Task 7: Rendered QA, full verification, and Hallmark handoff

**Files:**
- Modify: `.hallmark/log.json` (ignored metadata)
- Review: all changed production and test files

**Interfaces:**
- Consumes: completed implementation.
- Produces: screenshot evidence and verified release state.

- [ ] **Step 1: Run migrations and full automated checks**

Run: `php artisan migrate --no-interaction && php artisan test --compact && npm run build && php artisan view:cache`

Expected: all commands exit 0.

- [ ] **Step 2: Run Hallmark slop test**

Load `references/slop-test.md`, audit the changed UI, and correct any failing applicable gate before continuing.

- [ ] **Step 3: Validate representative rendered pages**

Use the Browser plugin at `320`, `375`, `414`, `768`, `820`, `1024`, `1180`, and desktop width. Verify Dashboard, POS, cashier Stock-In, a form page, and Inventory/table page for identity, meaningful DOM, no overlay, console health, document overflow, touch targets, single-line actions, drawer behavior, and a real stock-in mutation.

- [ ] **Step 4: Update Hallmark metadata**

Prepend `{ "date": "2026-07-14", "macrostructure": "Catalogue", "theme": "existing TailAdmin tokens", "enrichment": "none", "brief": "Global tablet UI and focused cashier stock-in" }` to `.hallmark/log.json` and keep at most 20 entries.

- [ ] **Step 5: Final diff review**

Run: `git diff --check && git status --short`

Expected: no whitespace errors; only scoped implementation, tests, docs, and migrations are changed.
