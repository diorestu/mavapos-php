# Midtrans subscription billing

New subscription invoices use Midtrans Snap.js popup checkout on the Billing page, with hosted checkout retained as a fallback. Existing SumoPod/Pakasir invoices keep their historical data and matching legacy notification endpoints. This is invoice-based monthly/yearly payment; it does not enable automatic card debits or recurring auto-renewal.

## Configuration

Set server-side `.env` values (never commit real credentials):

```dotenv
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=YOUR_SANDBOX_SERVER_KEY
MIDTRANS_CLIENT_KEY=YOUR_SANDBOX_CLIENT_KEY
MIDTRANS_SIGNATURE_SECRET_KEY=
MIDTRANS_TIMEOUT=20
```

- Sandbox Server Keys start with `SB-Mid-server-`; production Server Keys start with `Mid-server-`. The configured key must match the selected environment.
- Snap.js requires a Client Key from the same merchant and environment as the Server Key. The supplied Client Key is passed unchanged to the SDK; prefixes are not used to infer or reject its environment. Only this public Client Key is placed in the SDK script tag. Server Keys and signature secrets never go to the frontend.
- When the Client Key is absent, the popup is not initialized and the existing hosted checkout link stays available. Do not invent a sandbox Client Key by adding a prefix to a production key.
- `MIDTRANS_SIGNATURE_SECRET_KEY` preserves the separately supplied merchant value but is **not used by Snap**. Snap notification signatures use the active `MIDTRANS_SERVER_KEY`: `SHA512(order_id + status_code + gross_amount + ServerKey)`. Do not substitute a production key for a sandbox webhook signature secret.
- Provider endpoints are fixed to Midtrans sandbox/production domains; no caller-supplied gateway URL is used.
- After changing `.env`, run `php artisan config:clear` for local development, or `php artisan config:cache` during deployment. Restart long-lived application workers if applicable. Copy environment values separately to the deployed server.

See official [Snap integration guide](https://docs.midtrans.com/docs/snap-snap-integration-guide), [notification verification](https://docs.midtrans.com/docs/https-notification-webhooks), and [GET transaction status](https://docs.midtrans.com/reference/get-transaction-status).

## Dashboard configuration

In the **matching sandbox/production Midtrans dashboard**, set Payment Notification URL to:

```text
https://YOUR_PUBLIC_BACKEND_DOMAIN/midtrans/notification
```

Use a publicly accessible HTTPS domain. A local `.test` domain or localhost cannot receive Midtrans notifications; use a public staging backend or HTTPS tunnel for local webhook testing. The finish URL is sent per transaction from Laravel's configured `APP_URL` via `/billings`; set APP_URL correctly for the deployment. The checkout return selects the tenant-owned invoice by order_id and retrieves its authoritative API status before rendering. Redirect parameters never grant payment by themselves.

The notification route is CSRF-exempt, responds with JSON, and authenticates notifications using Midtrans's signature. No shared secret belongs in the notification URL. Midtrans's separate BI SNAP bank-notification protocol is not used here.

## Payment flow

1. Owner/admin selects a subscription plan and billing cycle, then clicks **Buat Pembayaran Midtrans**.
2. Backend creates a merchant-scoped local invoice and requests a Snap token using Basic authentication with Server Key as username and an empty password. The next Billing render automatically opens the popup once for that invoice, after the SDK has loaded.
3. The invoice list provides **Bayar via Midtrans**, which reopens `window.snap.pay(token)` on the same page. `snap.js` is loaded from `app.sandbox.midtrans.com` for sandbox or `app.midtrans.com` for production. **Buka Halaman Pembayaran** remains available alongside the popup button, including after asynchronous SDK errors. Payment methods depend on the methods enabled on the merchant's Midtrans account. This implementation does not force QRIS-only checkout or enable methods not activated in that account.
4. A notification, checkout return, automatic page check, or **Cek** fetches the authoritative transaction status from Midtrans and verifies order ID, integer IDR amount, transaction identity, and environment before updating the invoice.
5. Verified settlement or accepted card capture marks the invoice `completed` and activates the existing subscription metadata. Initial subscription period/date calculations retain the application's existing behavior (period starts on invoice creation). Renewal/upgrade business rules are unchanged.

All Snap.js callbacks (`onSuccess`, `onPending`, `onError`, `onClose`) navigate to the server-generated verification URL for the selected invoice. Callback status, order IDs, and redirect URLs supplied by browser results are never used to grant access. Closing a popup does not cancel an invoice by itself; the backend checks its real status. Automatic polling pauses while the popup is active so it cannot reload over the payment form.

The signature does not include transaction_status or fraud_status. Therefore a valid signature alone is insufficient: backend status retrieval is mandatory. A `capture` with challenge/deny fraud status never activates a subscription. The original `paid_at` is retained on repeated notifications and later pending/expiry messages do not downgrade a paid invoice.

Full refund/chargeback reversals produce `refunded`/`chargeback`; they no longer qualify as active paid invoices. Partial refunds preserve an already-paid invoice's status and remain recorded in provider status metadata; financial/manual review remains necessary. Existing other paid invoices can still independently grant subscription access under the application's existing membership rules.

Local Snap page lifetime is 24 hours. GET Status can return application status_code 404 in HTTP 200 or 404 before a payment method is chosen. A fresh unused invoice remains pending. An unused invoice past its local deadline becomes expired when checked, under a row lock; expiry does not overwrite an actual remote pending/settled payment. [Midtrans notes](https://docs.midtrans.com/reference/expire-a-snap-session) that a Snap page expiration itself does not trigger a notification.

If gateway creation fails, the local invoice remains visible with status `failed` and without a payment URL; a new request generates a new invoice. Exception details and credentials are not shown to the customer. A timeout can leave an unused sandbox/provider session; there is no automatic retry or duplicate charge request. The status-check action can reconcile a charge if the provider accepted the original request.

## Missed notifications and local sandbox testing

Midtrans cannot deliver notifications to localhost. While the Billing page is open, the application checks up to three most recent pending Midtrans invoices on initial page load, window focus/visibility return, and every 15 seconds for up to 20 rounds. Hidden pages and overlapping requests are skipped. After periodic polling stops, returning focus still performs a check. Manual **Cek** remains available. Status changes reload the page so invoice and subscription indicators agree.

Automatic checks POST to the existing merchant-scoped `/billings/{billing}/refresh` endpoint with session authentication, CSRF and JSON Accept header. The endpoint returns verified local payment status, never provider payloads or credentials. The JSON/form check and checkout-return verification share one limit of 30 requests/minute/user. Provider outages do not mark a payment successful.

Midtrans appends `order_id` when returning to `/billings`. That selector is validated and looked up within the logged-in merchant; the server queries Midtrans before updating anything. `transaction_status` or `status_code` from the redirect URL are not trusted. After verification, the return query is removed by redirecting to the normal Billing URL to prevent repeated checks on reload. For production, public HTTPS notification configuration is still required for updates when nobody has the billing page open.

## Routes and deployment

| Method | Route | Access |
| --- | --- | --- |
| GET | `/billings` | Existing authenticated owner/admin access |
| POST | `/billings` | Create Midtrans subscription invoice; existing CSRF |
| POST | `/billings/{billing}/refresh` | Owner/admin, same merchant; existing CSRF |
| POST | `/midtrans/notification` | Public signed notification; no session/CSRF required |

Legacy notifications cannot update Midtrans invoices. Historical SumoPod/Pakasir webhook URLs remain enabled only for their matching payment_provider values.

No new database migration or payment SDK is needed for this change. Deploy the application and compiled assets, set the environment credentials, refresh config, then configure the dashboard notification URL. The `.env` file on the developer machine is not pushed to Git and does not configure production automatically.

## Verification

Automated tests cover all four plan/cycle amounts, sandbox API request shape, checkout failure, invalid/missing signatures, amount mismatches, fraud challenge, authoritative status retrieval, duplicate/out-of-order notifications, refund finality, tenant isolation, and HTTP 200/404 status-not-found behavior including expired unused sessions.

An actual sandbox Snap checkout was created using the supplied sandbox Server Key during implementation. No payment was submitted. Snap.js callback, token isolation and SDK fallback behavior are covered separately by backend/frontend tests. The real sandbox SDK popup was also opened using the exact Client Key supplied by the merchant, without prefix modification or a payment submission. Completing a sandbox payment and receiving a public webhook must still be tested against the configured staging/public notification URL before switching production on.

Official popup reference: [Snap.js popup integration](https://docs.midtrans.com/docs/snap-snap-integration-guide). Snap tokens are rendered only for the authenticated merchant's pending invoices in the active environment; completed invoices and foreign merchants do not expose them.
