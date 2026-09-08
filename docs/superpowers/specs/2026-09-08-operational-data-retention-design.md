# Operational Data Retention Design

## Goal

Prevent normal application actions from permanently deleting MavaPOS operational data while keeping archived records out of normal working lists and POS workflows.

## Scope

Soft delete applies to these business aggregates: users, product categories, products, suppliers, customers, raw materials, branches, cashier shifts, POS sales, purchase orders, and stock transfers.

Supporting detail and technical tables do not receive an independent delete flow: product variants, branch inventories, recipe items, stock movements, POS sale items, POS sale payments, POS raw-material usages, payrolls, notification reads, sessions, jobs, cache, tokens, and uploaded files remain retained or use their existing operational lifecycle. API tokens and replaced image/logo files may still be permanently revoked or removed because they are credentials or obsolete binary assets rather than accounting records.

## Data-retention policy

- Each scoped aggregate receives a nullable `deleted_at` column and Laravel's `SoftDeletes` trait.
- Normal Eloquent queries exclude archived records, so deleted users cannot authenticate and deleted products cannot be sold.
- Archive operations must call model `delete()` only; production code must not call `forceDelete()` for scoped aggregates.
- A product archive must not delete variants or branch inventories. Their records stay intact to preserve a future restore and historical stock references.
- A cashier-shift archive must not delete POS sales, items, payments, or raw-material usage. The UI must describe it as an archive, not as a deletion.
- Existing void flows continue to mark sales voided rather than deleting them.
- Audit-facing relations that need a deleted parent name use `withTrashed()` explicitly; ordinary selection lists retain Laravel's default exclusion.

## UI and compatibility

- Existing DELETE routes remain for backwards compatibility, but their visible labels/messages change from "Hapus" to "Arsipkan" or "Nonaktifkan" where applicable.
- There is no permanent-delete action in the application UI.
- Existing migrations retain their foreign-key cascades for database-level integrity, but ordinary soft deletes do not execute those cascades. The system must not introduce a force-delete path.

## Verification

- Feature tests demonstrate that archiving a user, product, category, and closed cashier shift sets `deleted_at` while their dependent operational rows remain in the database.
- Feature tests demonstrate archived users cannot sign in and archived products cannot appear in POS payloads.
- Existing product, POS, and cashier-shift tests remain green.
- `php artisan migrate:fresh --seed` and the focused test suite run successfully using SQLite.
