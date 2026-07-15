# Cashier-Owned Active-Shift Transaction Void Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow cashiers to void only their own transactions while the related cashier shift remains active, without reducing owner/admin access.

**Architecture:** Expand the existing void route's role middleware, then enforce ownership and active-shift authorization inside the locked database transaction in `TransactionVoidService`. Render the existing void action per sale row using the same eligibility rules.

**Tech Stack:** Laravel 12, Eloquent, Blade, Alpine.js, Pest.

## Global Constraints

- Owner and admin retain access to every non-voided transaction in the active branch.
- Cashiers may void only sales where `user_id` matches their account and `shift.closed_at` is null.
- Unauthorized cashier requests return HTTP 403 before inventory or audit state changes.
- Existing reason validation, branch scope, stock restoration, audit, duplicate-void protection, and summary recalculation remain unchanged.
- Preserve the untracked `Archive.zip` file without modification.

---

### Task 1: Backend Authorization

**Files:**
- Modify: `tests/Feature/TransactionVoidTest.php`
- Modify: `routes/web.php`
- Modify: `app/Services/TransactionVoidService.php`

**Interfaces:**
- Consumes: `TransactionVoidService::void(PosSale $sale, int $branchId, User $actor, string $reason): PosSale`
- Produces: the same interface with role-sensitive authorization enforced inside the transaction.

- [ ] **Step 1: Write failing request tests**

Add tests proving a cashier can void their own active-shift sale, receives 403 for another cashier's sale, and receives 403 for their own sale after its shift closes. Each denial must also assert `voided_at` remains null and stock remains unchanged.

- [ ] **Step 2: Run tests and verify RED**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php`

Expected: the own-sale request fails with route middleware 403, demonstrating cashier access is not implemented.

- [ ] **Step 3: Implement the minimum backend rule**

Move `sales.void` under middleware `role:owner,admin,kasir`. After the service reloads and locks the sale with its shift, enforce:

```php
if ($actor->hasRole('kasir')) {
    abort_unless($sale->user_id === $actor->id && $sale->shift?->closed_at === null, 403);
}
```

Keep this check before stock restoration and audit updates.

- [ ] **Step 4: Run tests and verify GREEN**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php`

Expected: all transaction void tests pass.

- [ ] **Step 5: Commit backend behavior**

```bash
git add routes/web.php app/Services/TransactionVoidService.php tests/Feature/TransactionVoidTest.php
git commit -m "feat: allow cashier to void own active-shift sales"
```

### Task 2: Per-Row Void Action

**Files:**
- Modify: `tests/Feature/TransactionVoidTest.php`
- Modify: `resources/views/pages/sales/index.blade.php`

**Interfaces:**
- Consumes: sale `user_id`, loaded `shift.closed_at`, and authenticated user role/ID.
- Produces: a row-level `$canVoidSale` boolean used by the existing Alpine void modal trigger.

- [ ] **Step 1: Write the failing view test**

Add a test with two open-shift transactions from different cashiers. Assert the authenticated cashier sees `Batalkan Transaksi` exactly once for their own row and does not receive a void action on the other cashier's row. Assert owner/admin still see the action.

- [ ] **Step 2: Run tests and verify RED**

Run: `php artisan test --compact tests/Feature/TransactionVoidTest.php`

Expected: cashier does not see the action because the current template allows only owner/admin.

- [ ] **Step 3: Implement row eligibility**

Inside the sale loop, compute eligibility from the authenticated user and row:

```php
$canVoidSale = ! $sale->voided_at && (
    auth()->user()?->hasRole(['owner', 'admin'])
    || (auth()->user()?->hasRole('kasir')
        && $sale->user_id === auth()->id()
        && $sale->shift?->closed_at === null)
);
```

Render the existing button only when `$canVoidSale` is true.

- [ ] **Step 4: Run focused tests and formatter**

Run: `vendor/bin/pint --dirty && php artisan test --compact tests/Feature/TransactionVoidTest.php`

Expected: formatting passes and all transaction void tests pass.

- [ ] **Step 5: Commit UI behavior**

```bash
git add resources/views/pages/sales/index.blade.php tests/Feature/TransactionVoidTest.php
git commit -m "feat: show cashier void action for eligible sales"
```

### Task 3: Full Verification and Delivery

**Files:**
- Verify only: all changed files and repository state.

**Interfaces:**
- Consumes: completed backend and UI behavior.
- Produces: verified `main` branch pushed to `origin/main`.

- [ ] **Step 1: Run complete verification**

Run: `composer run test && npm run build && git diff --check`

Expected: all tests pass, Vite build succeeds, and no whitespace errors are reported.

- [ ] **Step 2: Confirm repository scope**

Run: `git status --short`

Expected: only the user-owned untracked `Archive.zip` remains; it is not staged or committed.

- [ ] **Step 3: Push main**

```bash
git push origin main
git ls-remote origin refs/heads/main
```

Expected: remote `main` resolves to the same commit as local `HEAD`.
