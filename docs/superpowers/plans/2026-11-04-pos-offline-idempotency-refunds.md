# POS Offline, Idempotency and Refunds Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development or superpowers:executing-plans task by task.

**Goal:** Add safe offline checkout/sync, duplicate protection and partial returns to MavaPOS.
**Architecture:** Durable browser outbox wraps existing checkout. A transaction-bound idempotency middleware guards checkout; partial refunds are separate immutable models/services with branch-scoped UI. Parent integrates offline context validation, client flow and reports.
**Tech Stack:** Laravel 12, PHP 8.2+, SQL, Alpine, IndexedDB, service worker, Node tests, Pest.
**Spec:** docs/superpowers/specs/2026-11-04-pos-offline-idempotency-refunds.md

## Global Constraints
Preserve existing pending edits. Never loosen branch/tenant access. No new external payment calls. Use current checkout calculations. Money uses integer rupiah. No production migrations or commits.

## Review Focus
- Network timeout after server commit replays same stable ID.
- Pending orders stay visible across reload/logout and never auto-sync under another identity.
- Price/stock changes and closed shifts cannot silently move offline transactions.
- Multiple partial returns cannot exceed original qty or net payment, including rounding and 100% merchant deductions.
- Returning prepared goods does not recreate consumed raw materials automatically.

## Tasks
- [x] Idempotency middleware/migration + checkout route integration + feature tests (independent agent).
- [x] IndexedDB outbox module and worker offline shell/scoping + Node tests (independent agent).
- [x] Partial refund models/migration/service/controller/sales UI + tests (independent agent).
- [x] Parent: integrate outbox into POS, offline capture context and timestamp/quote validation, pending UI and receipt.
- [x] Parent: integrate refund effects in report summaries/Excel refund sheet and cash recap.
- [x] Parent: review contracts, run feature/Node/full tests and build; repair and document limits.

## Verification outcome
267 PHP tests /3092 assertions and25 Node tests passed; Vite build and Blade compilation passed. Playwright on isolated temporary SQLite confirmed offline persistence/reload/sync, partialrefund/restock and committed-but-lost-response replay with stable shift totals. Usage and deployment: docs/pos-offline-and-refunds.md.
