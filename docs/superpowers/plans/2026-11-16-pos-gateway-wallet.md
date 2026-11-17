# POS Gateway and Wallet Implementation Plan

**Goal:** New POS Midtrans checkout, merchant-isolated wallet ledger, and manually processed withdrawal requests.
**Spec:** docs/superpowers/specs/2026-11-16-pos-gateway-wallet-design.md
**Execution:** Inline, test first, current checkout; preserve uncommitted Midtrans subscription work.

- [x] Ledger: write failing WalletTest; add account/entry/payment/withdrawal migrations and models; implement atomic postings, fee reconciliation and withdrawal holds/transitions; verify isolation and idempotency.
- [x] POS lifecycle: write failing PosGatewayTest; add pending sale status, online dedicated gateway checkout with stable ID, reserve stock, verified payment/status reconciliation, immutable attribution and cancellation restoration; verify reports and gateway protections.
- [x] Notifications/reconciliation: dispatch persisted POS order IDs before subscription invoices; add bounded scheduler command and authenticated status return; verify failed notifications do not grant funds.
- [x] UI: payment selection/Snap popup, pending payment screen/resume link; merchant wallet with account/outlet/date filters and owner requests; superadmin net reconciliation and manual payout review; add navigation.
- [x] Docs/review: document actual funds versus internal buckets/fees/sandbox, scope restrictions and operator workflow; independent review, focused/full backend/frontend tests, build and browser checks.

## Verified outcome

- Full backend: 393 tests / 3967 assertions pass at 512 MB PHP memory.
- Frontend Node: 42 tests pass; Vite build passes.
- Browser isolated database: POS pending → verified payment → merchant wallet gross → net fee reconciliation → reserved request → superadmin simulated payout completed; final Rp19.000, another merchant hidden, bank number masked, desktop/mobile checked.
- Local SQLite backup created before additive migration; migration rollback/reapply tested in a separate disposable database. No production deployment or real payout performed.
- Review fixes include online-merchant payload coercion, post-payment bank reversals, tenant-safe stock release and interrupted worker recovery.
- Existing report-description fixture pinned away from midnight so description checks do not depend on the wall-clock day boundary.
