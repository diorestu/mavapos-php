# Transaction Void, Cashier Stock-In, and Shift Recap Design

## Goal

Add an owner/admin-only transaction void workflow that preserves a complete audit trail and reverses inventory effects, implement the previously approved focused cashier stock-in flow, and show a printable final sales and cash recap after both normal and forced cashier-shift closure.

## Existing Foundations

- POS checkout, shift opening/closing, sales history, and shift aggregates already exist.
- Normal and forced shift close already calculate sales count, gross sales, discounts, net sales, and totals by payment method.
- Receipt printing already supports browser HTML, Web Bluetooth ESC/POS, and IMIN InnerPrinter paths.
- Cashier stock-in follows `docs/superpowers/specs/2026-07-14-global-tablet-ui-cashier-stock-in-design.md` and its implementation plan. This design does not broaden cashier inventory permissions beyond that approved scope.

## Transaction Void

### Authorization and entry point

- Only authenticated `owner` and `admin` users may void a transaction.
- The action is exposed on the active branch's Data Penjualan page and protected independently by route middleware and server-side branch validation.
- Cashiers may continue to view Data Penjualan according to existing access rules but never see or invoke the void action.
- The confirmation dialog identifies the invoice and total, explains that inventory will be restored, and requires a non-empty void reason.

### Audit model

`pos_sales` gains nullable void audit fields:

- `voided_at`: when the transaction was voided.
- `voided_by_user_id`: the owner/admin who performed the void; it uses `nullOnDelete` so deleting a staff account does not erase sales history.
- `void_reason`: the required business reason.

The original sale and line items remain unchanged and readable. A voided sale is visibly labelled `Dibatalkan` in sales history. The action is idempotent from a business perspective: a second void request is rejected and cannot restore inventory twice.

### Inventory reversal

A focused transaction-void service performs the reversal inside one database transaction:

1. Lock the sale and verify that it belongs to the active branch and is not already voided.
2. Lock each affected branch product or variant inventory row.
3. Add each sold quantity back to its branch inventory and synchronize the compatibility stock column used by the current POS flow.
4. Restore recipe raw-material quantities consumed by the sale. New checkouts persist a per-sale raw-material usage snapshot so later recipe edits cannot change the reversal amount. For sales created before snapshots exist, calculate the fallback amount from the current recipe at void time and mark the reversal note as a legacy calculation.
5. Create an `in` stock movement for every restored sellable item. The movement references the original invoice and states that it is a transaction-void reversal.
6. Persist the void audit fields.
7. Recalculate the related shift aggregates while excluding voided sales.

If any reversal step fails, the complete database transaction rolls back. No partial stock, movement, audit, or shift-total changes remain.

### Reporting behavior

- Default sales summaries, dashboard metrics, shift aggregates, reports, and report downloads exclude voided sales.
- Data Penjualan retains voided rows for audit and visually distinguishes them from active sales.
- Filtering and pagination continue to include the audit rows, while monetary summary cards aggregate only active sales.
- A void performed after shift closure still recalculates and stores that closed shift's final totals.

## Cashier Stock-In

Implement the already approved focused flow from `2026-07-14-global-tablet-ui-cashier-stock-in-design.md`:

- Add a cashier-visible `Stok Masuk` entry point and a dedicated page.
- Allow search and selection by product name, SKU, barcode, or variant.
- Require a positive integer quantity; reference and note remain optional.
- Update only the active branch's product or variant inventory inside a locked database transaction.
- Record stock before, stock after, branch, item, and authenticated actor in `StockMovement`.
- Keep cashiers forbidden from full Inventory, stock-out, absolute corrections, purchase price changes, purchase orders, stock transfers, product management, and raw-material management.
- Preserve owner, admin, and warehouse inventory behavior.

## Shift Closing Recap

### Shared recap payload

Normal close and owner/admin forced close return or expose the same final recap contract:

- Shift ID.
- Branch name.
- Cashier name.
- Open and close timestamps.
- Transaction count.
- Gross sales.
- Discount total.
- Net sales.
- Cash total.
- QRIS total.
- Card total.
- Opening cash amount.
- Expected cash in drawer, calculated as opening cash amount plus cash sales.
- Closing note.
- Store identity, receipt options, and printer connection options needed by the existing print paths.

Both close flows compute and persist their final aggregates before producing this payload.

### Normal close interaction

After a staff member successfully closes their own shift:

1. The close confirmation dialog is replaced by a recap popup.
2. The popup shows the final sales and expected-money breakdown.
3. `Print Rekap` prints through the configured browser, Bluetooth, or IMIN path.
4. `Lewati` closes the recap and returns the POS to the start-shift state.

The start-shift modal must not cover the recap. It becomes available only after the recap is dismissed.

### Forced close interaction

After owner/admin successfully force-closes an active shift from Shift Kasir:

1. A recap popup opens for the shift that was just closed.
2. It shows the same fields and uses the same print formatting as normal close.
3. Dismissing the popup refreshes the shift list so the final stored values remain visible.

### Print behavior

- Browser mode opens a compact thermal-print window.
- Bluetooth mode sends an ESC/POS recap payload through the existing configured characteristic.
- IMIN mode sends the recap through the existing local InnerPrinter service.
- Recap printing does not send a cash-drawer-open command because no new payment is being accepted.
- A printer failure never reopens or rolls back the already closed shift.
- On failure, the popup remains open, shows the error, and offers `Coba Print Lagi` and `Lewati`.
- The recap remains manually printable until its popup is dismissed; no print-history subsystem is added.

## UI and Accessibility

- Void and print confirmations use the existing Blade, Tailwind, Alpine, dark-mode, and modal patterns.
- Destructive void actions use explicit copy and an error colour, while the final confirmation includes invoice, amount, and inventory-restoration consequences.
- Async actions prevent duplicate submission and expose loading, success, and error states.
- Dialogs support keyboard focus, Escape dismissal when safe, visible `focus-visible` rings, and `aria-live` status text.
- Transaction status is conveyed by text and iconography, not colour alone.
- Tablet and mobile sizing follows the approved global tablet design without adding a second component system.

## Error Handling and Security

- Return HTTP 403 for non-owner/admin void attempts.
- Return HTTP 404 for a sale outside the active branch so cross-branch data is not disclosed.
- Return HTTP 409 when a sale has already been voided.
- Return HTTP 422 for a missing or invalid void reason.
- Lock sale, inventory, raw-material, and shift records where necessary to prevent double reversal or inconsistent totals.
- Validate all role and branch boundaries in backend code; menu and button visibility are not treated as authorization.
- A close or force-close response is successful once the database state is committed. Printer connectivity is handled as a separate client-side outcome.

## Test Strategy

### Transaction void

1. Owner and admin can void a sale in the active branch.
2. Cashier and warehouse users receive HTTP 403.
3. A sale in another branch is not exposed or modified.
4. Void reason, actor, and timestamp are recorded while the sale and items remain present.
5. Product and variant branch stock are restored exactly once.
6. Recipe raw-material stock is restored.
7. Reversal stock movements reference the original invoice.
8. New checkouts persist raw-material usage snapshots; a legacy sale without snapshots uses the explicitly marked current-recipe fallback.
9. A second void request returns HTTP 409 without changing stock.
10. Active sales summaries, dashboards, reports, and downloads exclude voided sales.
11. Sales history displays voided rows as audit records.
12. Open and closed shift totals are recalculated after a void.

### Cashier stock-in

Use all backend, rendered-view, permission, branch-isolation, and actor-audit cases from the approved cashier stock-in specification and plan.

### Shift recap

1. Normal close returns the final shared recap payload and opens the recap popup.
2. Forced close exposes the same recap fields and opens its popup.
3. Expected cash in drawer equals opening cash plus cash sales.
4. QRIS and card totals remain separate from expected drawer cash.
5. The recap contains closing note and localized timestamps.
6. Printer failure leaves the shift closed and the popup retryable.
7. Browser, Bluetooth, and IMIN recap builders include the same financial fields.
8. Recap builders do not emit a cash-drawer command.

### Completion checks

- Run focused feature tests for sales void, cashier stock-in, and cashier shifts.
- Run the complete Laravel test suite.
- Run the frontend production build.
- Run formatting and `git diff --check`.
- Render and interact with Data Penjualan, cashier Stok Masuk, POS close, and Shift Kasir forced-close flows at representative mobile, tablet, and desktop widths.

## Out of Scope

- Permanently deleting a sale or its line items.
- Letting cashiers void their own transactions.
- Editing a completed transaction's items, payment method, discount, or totals.
- Refunding money through a payment provider.
- Reopening a closed shift after voiding a sale.
- Printing item-level shift details; the recap is an aggregate sales and expected-money summary.
- Opening the cash drawer during recap printing.
- Adding cashier access to stock-out, corrections, purchase prices, purchase orders, transfers, product management, or raw-material management.
