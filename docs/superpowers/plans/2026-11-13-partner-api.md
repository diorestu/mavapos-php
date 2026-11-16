# Partner API Implementation Plan

> Implement inline with test-first verification.

**Goal:** Read-only third-party merchant reporting API, signed creation webhooks and isolated fixture sandbox.
**Architecture:** Dedicated hashed keys; explicit merchant queries; shared serializers; transactional webhook outbox and scheduled sender.
**Tech Stack:** Laravel 12, PHP 8.2, Pest, SQLite tests; existing dependencies only.
**Spec:** docs/superpowers/specs/2026-11-13-partner-api-design.md

## Global Constraints
- Read-only business data; never accept partner credentials on mobile/web mutation routes.
- ISO8601 offset timestamps, integer IDR, 60 requests/minute, 100 rows/page, max 31-day half-open sale window.
- No production data or network delivery from sandbox.

## Review Focus
- Foreign merchant outlet/sale IDs must return 404.
- Refund changes on historical sales must remain discoverable.
- Variant metadata may be changed/deleted; distinguish live attributes from captured names/prices.
- SSRF, duplicate sends, key revocation, failed receiver and rollback must not lose or leak transactions.
- Empty sets, invalid timestamp offsets and boundary dates must have predictable responses.

## Tasks
- [x] Write failing PartnerApiTest covering contract, merchant isolation, keys, pagination, sandbox, filters and summaries; run RED.
- [x] Add key schema/model/authentication/throttle/commands and management endpoints; add API controllers/serializers and sandbox fixtures; run GREEN.
- [x] Write failing PartnerWebhookTest for registration/ownership/signature/retries/revocation/rollback; run RED.
- [x] Implement webhook/outbox schema, observer, protected HTTPS sender, scheduled delivery and sandbox test event; run GREEN.
- [x] Write runnable API guide, example curl, webhook receiver verification, OpenAPI and deploy/sandbox instructions.
- [x] Format touched PHP; run new and existing relevant tests then full suite; review changes and report limitations.

## Verification outcome

- `php -d memory_limit=512M vendor/bin/pest --compact --colors=never`: 334 passed, 3603 assertions. The default 128 MB process exhausted memory in existing XLSX generation; rerun at 512 MB completed.
- `vendor/bin/pint --dirty --test`: passed.
- `git diff --check`: passed.
- Route and scheduler registration checked; OpenAPI JSON and all local references verified. Full OpenAPI schema validator is not installed.
- Independent review completed; payment normalization finding fixed and covered by actual checkout integration tests.
- No production deployment, real API credentials, or live outbound receiver tests performed.
