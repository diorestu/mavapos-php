# MELT Annual MavaPOS Subscription Quotation Design

## Deliverable

Create a client-ready two-page Microsoft Word quotation addressed to MELT and issued under the MAVAPOS product name. Save the final document as `output/doc/quotation-mavapos-melt-2026.docx`.

## Commercial Terms

- Product: MavaPOS annual subscription.
- Subscription period: 12 months from activation.
- Price: Rp4.000.000 nett per year.
- Negotiation status: negotiable.
- Tax treatment: nett; no additional tax is added to the quoted total.
- Payment: annual payment in advance.
- Offer validity: 14 calendar days from the quotation date.
- Included services: account activation, initial configuration, basic onboarding, and standard support during the subscription period.
- Exclusions: cashier hardware, printer hardware, paper, network equipment, third-party service fees, custom integrations, custom feature development, and on-site work unless agreed separately.

## Recipient and Issuer

- Recipient: MELT.
- Issuer/product: MAVAPOS.
- Use `public/logo.png` as the MAVAPOS brand mark.
- Do not invent recipient contacts, issuer legal entity, tax number, bank account, personal contact, or signature name. Leave those elements out rather than displaying placeholders.

## Page Structure

### Page 1 - Quotation

- MAVAPOS logo and document title.
- Quotation number using the deterministic format `QTN/MAVAPOS/VII/2026/001`.
- Quotation date: 14 July 2026.
- Recipient: MELT.
- Short description of the annual MavaPOS subscription.
- Pricing table with one line item, quantity 1 year, unit price Rp4.000.000, and total Rp4.000.000.
- Prominent total labelled `Total Nett`.
- Negotiable note presented professionally without weakening the stated price.
- Payment, activation, validity, and exclusions in a compact terms section.

### Page 2 - Included Features

Group only repository-backed features into practical categories:

1. Dashboard and monitoring: operational metrics, store activity, global search, and notifications.
2. Cashier and sales: web POS, product/category search, cart, cash/QRIS/card payment methods, change calculation, cashier shifts, sales history, and customer display.
3. Product and inventory: products, categories, variants, SKU/barcode, buy/sell prices, minimum stock, stock-in/out history, branch stock, stock transfers, and purchase orders.
4. F&B operations: raw materials and product recipes with stock consumption.
5. Finance and reporting: expenses, sales/inventory/expense summaries, cashier performance, estimated profit, and PDF reports.
6. Administration: branches, owner/admin/cashier/warehouse roles, users, store identity, receipt settings, printer paths, dark mode, and PWA support.
7. Subscription operation: QRIS billing via Pakasir, payment-status refresh, and webhook updates.

End with a compact activation sequence: commercial agreement, payment, account/configuration setup, onboarding, and go-live.

## Visual Direction

- Two A4 portrait pages with consistent margins and no clipped tables.
- Modern-minimal business tone using MAVAPOS blue as the restrained accent.
- Use Aptos or another locally available professional sans-serif for reliable Word rendering.
- Strong price hierarchy, compact feature grouping, and sufficient white space.
- Avoid decorative gradients, invented testimonials, fabricated metrics, and stock photography.
- Use a footer with `MAVAPOS - Quotation for MELT` and page number.

## Verification

- Generate the DOCX with `python-docx`.
- Render DOCX to PDF and PNG using LibreOffice/Poppler or the bundled renderer.
- Inspect every rendered page for overflow, table splitting, uneven spacing, low contrast, missing logo, or accidental third page.
- Extract document text and confirm the recipient, quotation number, date, price, nett treatment, negotiable wording, validity, features, and exclusions are present.
- Remove intermediate rendering files after the final document is verified.
