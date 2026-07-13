# Cashier Access and Mobile Dashboard Design

## Goal

Give users with the `kasir` role a focused workspace containing Dashboard, Kasir, Data Penjualan, and Input Pengeluaran. The dashboard must prioritize the cashier's own daily activity and both dashboard variants must remain usable on small mobile screens.

## Access Rules

- Cashiers see four sidebar entries: Dashboard, Kasir, Penjualan, and Pengeluaran.
- Cashiers no longer see or directly access the Shift Kasir administration page.
- Existing owner and admin navigation and access remain unchanged.
- Existing role middleware continues to protect catalog, inventory, relationship, reporting, settings, branch, user, billing, and print-test routes.
- The cashier may open and submit the Pengeluaran page.
- The cashier may submit both operational expenses and stock-affecting expenses.
- The existing Data Penjualan page keeps its current branch-scoped behavior. This change does not narrow it to the cashier's own transactions.

## Expense Ownership

The existing `expenses.user_id` field is tenant ownership and cannot safely identify the employee who entered a record. Add a nullable `created_by_user_id` foreign key to `expenses`, use `nullOnDelete` so deleting a staff account does not delete financial history, and backfill existing rows where possible from the existing tenant owner so historical records remain visible to owner/admin.

On every new expense, the controller explicitly records the authenticated user's ID as `created_by_user_id`.

Expense visibility is role-sensitive and branch-scoped:

- Owner/admin: all expenses in the active branch.
- Cashier: only expenses in the active branch whose `created_by_user_id` equals the authenticated cashier ID.

Summary cards on the expense page must be calculated from the same visible query, preventing totals from leaking other users' data. Product choices remain available because cashiers are allowed to enter stock-affecting expenses. Stock movements and inventory updates continue to use the existing database transaction.

## Role-Specific Dashboard

`DashboardController` selects a dataset based on the authenticated role while retaining one dashboard route.

### Cashier dashboard

The cashier dashboard contains only operational information relevant to the logged-in cashier:

- Current shift state and opening time.
- Primary action to open or continue Kasir.
- Personal transaction count for today.
- Personal sales revenue for today.
- Personal expenses entered today.
- Recent personal sales.
- Recent personal expenses.

All sales metrics use `pos_sales.user_id` and the active branch. Expense metrics use `expenses.created_by_user_id` and the active branch. Owner subscription status, gross-profit charts, catalog size, low-stock counts, and business-wide annual metrics are not shown to cashiers.

### Owner/admin dashboard

The existing business-wide dashboard content and subscription banner remain available. Its data semantics remain unchanged.

## Mobile Layout

- Use a single-column content flow by default and expand progressively at tablet and desktop breakpoints.
- Metric cards use two columns only when the viewport can support readable labels and values.
- Primary cashier action spans the available width on small screens and has a touch-friendly height.
- Chart containers use a constrained responsive height and prevent canvas overflow.
- Recent-data sections render as compact mobile cards, while wider screens may retain tables.
- Avoid fixed minimum widths on the dashboard itself; horizontal scrolling is reserved for data tables that cannot be reduced without losing meaning.
- Preserve the current TailAdmin visual language, spacing tokens, dark mode, and typography.

## Components and Data Flow

- `MenuHelper` filters the standalone Dashboard item by role consistently with grouped items and grants `kasir` access to Pengeluaran.
- Routes grant `kasir` access to expense index/store and remove `kasir` from the Shift Kasir index route.
- `Expense` gains a creator relationship separate from its tenant-owner relationship.
- `ExpenseController` applies branch and creator filters before producing rows and summaries.
- `DashboardController` delegates owner/admin and cashier dataset construction to focused private methods.
- The dashboard Blade view renders a cashier-specific section or the existing owner/admin section from an explicit role/view flag.

## Error and Security Behavior

- A cashier opening `/cashier-shifts` directly receives HTTP 403.
- A cashier opening any existing owner/admin route continues to receive HTTP 403.
- A cashier cannot reveal another cashier's expense through query parameters because filtering is enforced in the controller query.
- Expense validation and the atomic stock-update transaction remain unchanged.
- Missing active shifts or empty recent activity render explicit empty states, not errors.

## Test Strategy

Feature tests must first fail against the current behavior and then cover:

1. Cashier navigation contains exactly Dashboard, Kasir, Penjualan, and Pengeluaran among business menus, without Shift Kasir or privileged menus.
2. Cashier can open and submit Pengeluaran, including a stock-affecting expense.
3. New expenses record `created_by_user_id`.
4. Cashier sees only their own expense rows and summaries within the active branch.
5. Owner/admin still see all active-branch expenses.
6. Cashier receives 403 for Shift Kasir.
7. Cashier dashboard shows personal daily sales, expense, and shift information without owner-only business or subscription information.
8. Owner/admin dashboard retains existing metrics and subscription state.
9. Dashboard markup includes the intended responsive layout classes and both frontend build and focused backend tests pass.

## Out of Scope

- A general permission-management system.
- Editing or deleting expenses.
- Narrowing Data Penjualan to the logged-in cashier.
- Redesigning pages outside the dashboard and the minimum expense-page text needed to communicate cashier-scoped history.
