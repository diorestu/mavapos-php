# Mava Backend

Mava Backend is a Laravel 12 admin application for managing store operations: products, product categories, inventory movement, suppliers, customers, store settings, and subscription billing through Midtrans Snap.js.

The UI is built with Blade, Tailwind CSS, Alpine.js, and Vite.

## Features

- Authentication with sign in, sign up, and logout.
- Dashboard summary for store metrics.
- Minimal POS cashier page with product search, category filter, cart, cash/QRIS/card payment modes, and change calculation.
- Product management with categories, variants, barcode, buy price, sell price, stock, and minimum stock.
- Inventory management with stock-in and stock-out transaction history.
- Customer and supplier management.
- Store settings for business identity and product behavior.
- SaaS billing module for Basic and Pro plans through Midtrans Snap.js popup checkout.
- Signed Midtrans notifications and verified manual payment status checks.
- New POS gateway checkout and merchant-isolated wallet with fee reconciliation and manually processed withdrawal requests. See [POS gateway and wallet guide](docs/pos-gateway-wallet.md).
- Read-only third-party REST API for merchant outlets, catalog and sales, with signed creation webhooks and fixture sandbox. See [Partner API guide](docs/partner-api.md) and [OpenAPI specification](docs/partner-api.openapi.json).
- Pest feature tests for core workflows.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js 18 or newer
- npm
- SQLite, MySQL, or PostgreSQL

## Installation

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create an environment file if it does not exist:

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

For local SQLite development, make sure the database file exists:

```bash
touch database/database.sqlite
```

Configure `.env`:

```env
APP_NAME="Mava Backend"
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Run migrations and seed sample data:

```bash
php artisan migrate --seed
```

The seeder creates sample products, categories, suppliers, customers, store settings, and a test user:

```text
Email: test@example.com
Password: password
```

## Running Locally

Start the full development stack:

```bash
composer run dev
```

This starts:

- Laravel development server
- Queue listener
- Laravel log tailing
- Vite development server

Open the application at:

```text
http://localhost:8000
```

You can also run services separately:

```bash
php artisan serve
npm run dev
```

## Frontend Build

Build production assets:

```bash
npm run build
```

## Subscription Billing and Midtrans

Subscription invoices at `/billings` use Midtrans Snap.js popup checkout with a hosted fallback. Configure matching sandbox/production credentials on the backend and register the public notification URL:

```text
https://your-domain.com/midtrans/notification
```

See [Midtrans subscription setup](docs/midtrans-subscription.md) for environment variables, dashboard setup, verification, and deployment. Existing legacy-provider invoices remain available; new invoices use Midtrans. This implements invoice payments, not automatic recurring debits.

## Main Routes

Authenticated routes:

```text
/                       Dashboard
/pos                    Cashier POS
/products               Products
/product-categories     Product categories
/inventory              Stock management
/customers              Customers
/suppliers              Suppliers
/settings               Store settings, including Billing access
```

Guest routes:

```text
/signin
/signup
```

## Testing

Run the test suite:

```bash
php artisan test
```

Or use the Composer script:

```bash
composer run test
```

The tests cover authentication, dashboard rendering, product and variant workflows, inventory movement, categories, suppliers, customers, settings, billing creation, and Midtrans checkout and verified notification handling.

## Useful Commands

Clear cached configuration:

```bash
php artisan config:clear
```

List routes:

```bash
php artisan route:list
```

Run migrations from scratch with seed data:

```bash
php artisan migrate:fresh --seed
```

Format PHP code with Pint:

```bash
./vendor/bin/pint
```

## Project Structure

```text
app/Http/Controllers     Web controllers
app/Models               Eloquent models
app/Services             External service clients, including Midtrans and legacy providers
database/migrations      Database schema
database/seeders         Sample/bootstrap data
resources/views          Blade pages and components
resources/css            Tailwind entrypoint
resources/js             Alpine/Vite JavaScript
routes/web.php           Web and webhook routes
tests/Feature            Feature tests
```

## Production Notes

Set production environment values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

Run deployment optimization after installing dependencies and building assets:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Make sure `/midtrans/notification` is reachable publicly and configured in the matching Midtrans dashboard so verified payments update subscriptions automatically.
