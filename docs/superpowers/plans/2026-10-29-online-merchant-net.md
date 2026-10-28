# Online Merchant Net Receipt Implementation Plan

> Execute inline with superpowers:executing-plans and TDD.

**Goal:** Record seller net receipts without selecting a payment method.
**Architecture:** Normalize merchant checkout to online_merchant, store net in paid_amount, derive merchant deduction from total minus paid_amount. Keep historical sales unchanged and summarize only new method receipts separately.
**Tech Stack:** Laravel, Blade, Alpine, Pest.
**Spec:** docs/superpowers/specs/2026-10-29-online-merchant-net-design.md

## Global Constraints
Preserve unrelated workspace edits, branch isolation, product inventory and direct-sale behavior. Do not commit or push automatically.

## Review Focus
Blank/zero/excessive net; forged methods and stale split rows; switching merchant/direct; historical merchant sales; void/correction recap and balanced journal.

### Task 1: Checkout and correction
- [x] Add failing integration cases in tests/Feature/OnlineMerchantSaleTest.php for no method, net receipt, invalid amounts and correction.
- [x] Run php artisan test --compact tests/Feature/OnlineMerchantSaleTest.php and observe failures.
- [x] Normalize merchant payments in PosController and SaleController; validate net in checkout/AdminSaleEditorService. Expose deduction on PosSale and receipt payload.
- [x] Re-run focused tests.

### Task 2: Financial summaries
- [x] Test net separation in shift, sales/report summary and balanced journal including sales discount.
- [x] Update CashierShiftSummaryService recap, SaleController and ReportController payment summaries. Journal debits net receipt and merchant deduction, credits gross sales; profit deducts merchant deduction once.
- [x] Update summary views and exports without overwriting existing discount-journal edits.

### Task 3: Cashier and correction UI
- [x] Hide direct payment/free controls when online, require net input, show deduction, reset stale amount on channel change in resources/js/app.js and cart.blade.php.
- [x] Update edit, receipt/printing, recap and report labels consistently.
- [x] Verify focused/full tests, build, diff check and browser interaction when available. Record existing failures separately.


## Verification and review
- Focused suite: 51 passed, 431 assertions (OnlineMerchantSaleTest, JournalDiscountTest, AdminSaleAndShiftEditTest, ReportTransactionDescriptionTest, ReportExcelExportTest, ComplimentaryPosSaleTest, LoyaltyCardCheckoutTest).
- Full suite: 200 passed, 7 pre-existing failures matching /tmp/mava-journal-tests-clean.log: ExampleTest (2), PosCashierShiftTest (3), StockTransferTest (2). Current log: /tmp/mava-online-full.log.
- Build, JS syntax, git diff --check passed. Pint passed for OnlineMerchantSaleTest, PosSale and CashierShiftSummaryService; existing formatting issues remain in other controllers.
- Chrome browser on isolated SQLite at 1440x1000 and 375x667: real checkout, no method/free/split controls for online, blank/excessive rejection, explicit zero, channel reset, correction, report totals; no page/console errors or horizontal mobile overflow. Browser plugin absent; used bundled Playwright with installed Chrome. Screenshots: /tmp/mava-online-desktop.png and /tmp/mava-online-mobile.png.
- Independent review found legacy classification conversion on unrelated edits. Fixed and covered QRIS/split preservation and explicit net conversion in regression tests and real browser; resolution reviewed without further findings.
- Ruling: reuse existing paid_amount and derive deduction only for online_merchant payment method; no schema migration or historical backfill.
- Ruling: legacy corrections preserve prior classification unless merchant/channel is deliberately changed or conversion is chosen; this avoids silently moving prior QRIS/cash/split receipts.
- Existing unrelated report COGS based on branch stock movements differs from financial-report item-based COGS; no changes made to that calculation.


### Follow-up: separate merchant revenue account and descriptions
- [x] Added OnlineMerchantJournalTest; observed two failures before implementation.
- [x] Separate Pendapatan Online Merchant credits, platform-specific receipt/deduction/discount/revenue descriptions, and legacy merchant revenue without altering payment classifications.
- [x] Verified screen journal and financial PDF rendered data, all four merchants, direct sales, historical cash/QRIS/split entries, and balanced debit/credit totals. Focused report and merchant suite: 30 passed, 321 assertions; PHP syntax, scoped Pint and diff check passed.
