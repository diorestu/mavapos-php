# MELT Annual Subscription Quotation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a visually verified two-page DOCX quotation for MELT offering a one-year MavaPOS subscription at Rp4.000.000 nett, negotiable.

**Architecture:** A temporary `python-docx` generator builds the document from the approved commercial terms and repository-backed feature list, using `public/logo.png` as the brand mark. The output is rendered to PDF and page PNGs for visual QA, then intermediate files are removed while the final DOCX remains under `output/doc/`.

**Tech Stack:** Python 3, python-docx, LibreOffice, Poppler, MAVAPOS PNG artwork.

## Global Constraints

- Recipient is `MELT`; issuer/product is `MAVAPOS`.
- Price is `Rp4.000.000` nett for 12 months and explicitly negotiable.
- Payment is annual in advance; offer validity is 14 calendar days from 14 July 2026.
- Do not invent recipient contacts, legal entity, tax number, bank account, contact person, or signature name.
- The final document must be exactly two A4 portrait pages and use only repository-backed product features.

### Task 1: Generate the DOCX

**Files:**
- Create: `tmp/docs/build_melt_quotation.py`
- Create: `output/doc/quotation-mavapos-melt-2026.docx`

- [ ] **Step 1: Build the document generator**

Use `python-docx` to configure A4 sections, 16 mm side margins, 15 mm top/bottom margins, Aptos typography, MAVAPOS blue accent, repeated footer, a pricing table on page 1, and grouped feature tables on page 2.

- [ ] **Step 2: Generate the DOCX**

Run: `/Users/user/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3 tmp/docs/build_melt_quotation.py`

Expected: `output/doc/quotation-mavapos-melt-2026.docx` exists and has non-zero size.

### Task 2: Render and verify

**Files:**
- Create temporarily: `tmp/docs/rendered/quotation-mavapos-melt-2026.pdf`
- Create temporarily: `tmp/docs/rendered/page-1.png`, `tmp/docs/rendered/page-2.png`

- [ ] **Step 1: Render DOCX to PDF and PNG**

Run LibreOffice headless conversion followed by `pdftoppm -png -r 144`.

- [ ] **Step 2: Inspect every page**

Confirm exactly two pages, visible logo, readable pricing table, no clipped text, no split feature group, balanced page margins, and a consistent footer.

- [ ] **Step 3: Verify required text programmatically**

Extract DOCX paragraphs and tables with `python-docx`; assert `MELT`, `QTN/MAVAPOS/VII/2026/001`, `Rp4.000.000`, `nett`, `negotiable`, `14 hari`, and all seven feature-group headings exist.

- [ ] **Step 4: Clean intermediates**

Remove `tmp/docs/rendered/` and the temporary generator after the final DOCX passes inspection.
