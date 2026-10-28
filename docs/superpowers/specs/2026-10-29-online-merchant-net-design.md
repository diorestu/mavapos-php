# Online Merchant net receipts

Approved in chat on 2026-10-29. Cashier selects a merchant and enters only the net amount received by seller, without cash/QRIS/card/split selection or change. Keep product totals and sales discounts; show total minus receipt as Potongan Online Merchant. Use a separate online_merchant payment method and existing paid_amount storage. Apply to checkout, correction, receipts, shift recap, sales, reports and journal; preserve branch boundaries and historical payment classifications.

Approaches: hiding payment controls alone would misclassify receipts; recording a separate payment method and net receipts preserves financial meaning. Selected: separate method. No automatic provider integration. Validate integer net receipts from zero to sale total; zero is explicit, blank is invalid.


## Follow-up: separate accounting revenue (2026-10-29)
User requested separate journal accounting for merchant income. Credit merchant sales to Pendapatan Online Merchant, retain Penjualan for direct sales, and identify each platform in the revenue, net receipt, merchant deduction and sales-discount descriptions. Apply to journal screen and summarized financial PDF. Historical merchant metadata determines revenue classification while original payment debits and stored transaction data stay intact.
