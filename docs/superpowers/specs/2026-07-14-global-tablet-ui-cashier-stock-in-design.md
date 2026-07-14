# Global Tablet UI and Cashier Stock-In Design

## Goal

Use the available width at tablet viewports across the authenticated MavaPOS application while preserving the current TailAdmin visual language, routes, dark mode, and desktop/mobile behavior. Add a focused stock-in flow that lets cashiers record newly arrived product stock without gaining broader inventory-management permissions.

## Design Direction

- Hallmark genre: modern-minimal.
- Hallmark macrostructure: Catalogue, selected because the application is dominated by operational lists, filters, tables, metrics, and inventory items.
- Theme: the existing TailAdmin colour and typography tokens.
- Navigation: the existing application sidebar; it remains a drawer below `xl` so tablet content retains the full viewport width.
- Footer: none, matching the existing authenticated application shell.
- Enrichment: none. Operational hierarchy and spacing provide the visual structure.
- Motion: motion-cut. Keep only functional drawer, modal, loading, and pressed-state feedback, with reduced-motion support.

## Responsive Foundation

The responsive changes apply to all authenticated application pages, not only Dashboard or POS.

### Viewport ranges

- Mobile: below `768px`; retain the current single-column and drawer-first behavior.
- Tablet: `768px` through `1279px`; increase useful content density and touch comfort without applying a visual transform or page-level scaling.
- Desktop: `1280px` and wider; retain the current persistent-sidebar behavior and existing maximum content width.

### Global tablet rules

- Keep the sidebar as an overlay drawer at tablet widths so it does not consume the content canvas.
- Increase the authenticated content shell's usable width and apply fluid inline padding instead of scaling the whole document.
- Use a minimum `44px` touch target for buttons, icon actions, inputs, selects, and pagination controls that are reachable on touch devices.
- Keep clickable labels on one line; action groups may wrap or reflow instead.
- Increase card padding and section gaps selectively at tablet widths. Do not give every container identical spacing.
- Promote suitable single-column grids to two or more columns from `md` or `lg` rather than waiting for `xl`.
- Use `minmax(0, 1fr)` for grid tracks and preserve `overflow-x: clip` on `html` and `body`.
- Preserve horizontal scrolling only for tables whose meaning would be damaged by card conversion.
- Keep numeric data tabular and prevent headings, labels, and controls from clipping or wrapping incorrectly.

## Page-Surface Rules

### Dashboard and metric pages

- Metric cards use two columns on tablet and may expand further on desktop.
- Paired charts use two columns when their minimum readable width fits; otherwise they remain stacked without fixed-width overflow.
- Chart containers lose tablet-only fixed minimum widths that create empty space or horizontal scrolling.
- Banners and primary actions use the available row width while maintaining readable measures.

### Forms and settings

- Related fields may form two columns on tablet, while long text inputs and textareas remain full width.
- Inputs and adjacent buttons share a consistent touch-friendly height.
- Modal content uses the tablet width without becoming edge-to-edge and keeps action buttons visible without horizontal clipping.
- Labels remain visible above controls; placeholders do not replace labels.

### Tables and index pages

- Filter/search toolbars use tablet rows instead of unnecessary vertical stacks.
- Row density remains compact, but action buttons receive a touch-safe hit area.
- Wide tables retain controlled horizontal scrolling; the document itself never scrolls horizontally.
- Pagination and bulk/action controls reflow around their labels rather than wrapping clickable text.

### POS

- Product and favourite grids increase their column count when tablet width supports readable cards.
- Product cards and primary controls become more touch-friendly without reducing the number of visible products through blanket scaling.
- The cart remains a drawer below `xl`; the persistent right-side cart remains a desktop behavior.

## Cashier Stock-In Flow

### Entry point

- Add a standalone cashier-visible menu item named `Stok Masuk`.
- Owner, admin, and warehouse users retain the existing full `Stok` / Inventory page.
- The cashier stock-in route uses a focused page instead of exposing the full Inventory page and its stock-out or correction actions.

### Interaction

1. The cashier opens `Stok Masuk`.
2. The cashier searches by product name, SKU, barcode, or variant.
3. The cashier selects one product or variant.
4. The cashier enters a positive integer quantity.
5. The cashier submits `Tambah Stok`.
6. The UI shows the stock change as `stok awal → stok baru`, refreshes the selected product, and keeps search ready for the next delivery item.

The required inputs are only product or variant and quantity. Reference and note are optional and live inside a collapsed `Tambahkan catatan` disclosure. Cashiers cannot enter or modify the purchase price.

### Permissions and data integrity

- Cashiers may open the focused stock-in page and submit stock-in only.
- Cashiers remain forbidden from the full Inventory index, stock-out, absolute stock correction, purchase-order, stock-transfer, product-management, and raw-material-management actions unless an existing role rule separately grants access.
- Owner, admin, and warehouse behavior remains unchanged.
- Stock is updated only for the active branch.
- The mutation runs inside a database transaction and locks the branch inventory row before calculating the new stock.
- Product variants update their branch-specific variant inventory; non-variant products update their branch-specific product inventory.
- Every stock-in creates a `StockMovement` containing the branch, item, quantity, stock before, stock after, optional reference, optional note, and occurrence time.
- Add a nullable `created_by_user_id` foreign key to stock movements. It records the authenticated user and uses `nullOnDelete` so staff deletion does not erase inventory history.

## Feedback and Error Behavior

- Submission enters an in-button loading state and blocks duplicate requests.
- Success is communicated by the visible stock delta and refreshed result; a redundant celebratory toast is not required.
- Validation identifies the field and tells the cashier how to fix it.
- An item removed or made unavailable before submission returns an explicit not-found or unavailable message without changing stock.
- Failed mutations roll back completely and preserve the cashier's entered quantity for correction or retry.
- Empty search results name what is missing and suggest checking the name, SKU, barcode, or branch.

## Accessibility and Interaction States

- Search, disclosure, product selection, quantity input, and submit controls support keyboard operation and visible `focus-visible` rings.
- Interactive controls cover default, hover where supported, focus, active, disabled, loading, error, and success states as applicable.
- State is not communicated by colour alone.
- Async status uses `aria-live="polite"`.
- Modal or drawer behavior preserves focus and Escape handling where used.
- `prefers-reduced-motion` reduces spatial motion to a short opacity change.

## Test Strategy

### Backend feature coverage

1. Cashier navigation includes `Stok Masuk`.
2. Cashier can open the focused stock-in page.
3. Cashier can add stock for a non-variant product in the active branch.
4. Cashier can add stock for a product variant in the active branch.
5. The stock movement records stock before, stock after, branch, item, and authenticated actor.
6. A stock-in request cannot change another branch's stock.
7. Quantity must be a positive integer.
8. Cashier receives HTTP 403 for full Inventory, stock-out, absolute correction, purchase orders, and stock transfers.
9. Owner, admin, and warehouse inventory access remains unchanged.

### Frontend and rendered coverage

- Run the frontend build and focused backend tests.
- Verify authenticated representative pages at `320px`, `375px`, `414px`, and `768px` per Hallmark's mobile floor.
- Verify tablet behavior at `820px`, `1024px`, and `1180px` before validating desktop at `1280px` or wider.
- Representative pages include Dashboard, POS, the focused cashier Stock-In page, a form-heavy page, and a wide-table index page.
- Checks cover page identity, meaningful content, framework-overlay absence, console health, document horizontal overflow, clipped controls, wrapped clickable labels, touch-target size, modal/drawer behavior, and one real stock-in interaction.

## Out of Scope

- Replacing TailAdmin, Public Sans, the existing icon language, or the application sidebar.
- Granting cashiers general Inventory, product, raw-material, purchase-order, or transfer access.
- Allowing cashiers to change purchase price, stock minimum, absolute stock, or stock-out quantities.
- Changing the desktop sidebar breakpoint or turning the tablet sidebar into a persistent rail.
- Rebuilding every table as cards on mobile.
- Adding decorative illustration, gradients, or non-functional animation.
