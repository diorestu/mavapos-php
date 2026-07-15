# Cashier-Owned Active-Shift Transaction Void Design

## Goal

Allow a cashier to void only transactions created by that cashier while the transaction's cashier shift is still active. Owner and admin retain branch-wide void access.

## Authorization Rules

- Owner and admin may void any non-voided transaction in the active branch.
- A cashier may void a transaction only when both conditions are true:
  - `pos_sales.user_id` equals the authenticated cashier's ID.
  - The related cashier shift is still active (`cashier_shifts.closed_at` is null).
- A cashier receives HTTP 403 when attempting to void another cashier's transaction or a transaction from a closed shift.
- Other roles remain blocked by route middleware.
- Branch scoping, required reason validation, duplicate-void protection, audit fields, stock restoration, raw-material restoration, and shift-summary recalculation remain unchanged.

## Architecture

Use the existing `sales.void` endpoint for owner, admin, and cashier. The route middleware provides the broad role boundary, while `TransactionVoidService` performs the ownership and active-shift checks on the locked sale record. Keeping the decisive check inside the transaction prevents direct API calls or concurrent shift closure from bypassing the rule.

The sales page computes void visibility per row. Owner/admin see the action for every eligible non-voided sale; a cashier sees it only on their own transaction whose shift is active.

## Data Flow

1. The authenticated user submits a reason through the existing void modal.
2. The service locks and reloads the sale, its shift, items, and stock-related records within the active branch.
3. If the actor is a cashier, the service verifies ownership and an open shift before changing stock or audit data.
4. The existing void process restores inventory and raw materials, records the actor/reason/time, and refreshes shift totals.
5. The UI reloads the sales history and shows the voided audit state.

## Error Handling

- Invalid or missing reason: HTTP 422.
- Sale outside the active branch: HTTP 404.
- Cashier does not own the sale: HTTP 403.
- Cashier's related shift is closed: HTTP 403.
- Sale already voided: HTTP 409.

## Test Coverage

- Cashier can void their own sale during their active shift.
- Cashier cannot void another cashier's sale.
- Cashier cannot void their own sale after the shift closes.
- Owner/admin retain existing void access.
- Cashier sees the void button only for an eligible owned transaction.
- Existing stock restoration, audit, reporting exclusion, and branch isolation tests remain green.
