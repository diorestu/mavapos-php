# Mava Backend

Mava Backend is a Laravel 12 admin application for managing store operations: products, product categories, inventory movement, suppliers, customers, store settings, and QRIS billing through Pakasir.

The UI is built with Blade, Tailwind CSS, Alpine.js, and Vite.

## Features

- Authentication with sign in, sign up, and logout.
- Dashboard summary for store metrics.
- Minimal POS cashier page with product search, category filter, cart, cash/QRIS/card payment modes, and change calculation.
- Product management with categories, variants, barcode, buy price, sell price, stock, and minimum stock.
- Inventory management with stock-in and stock-out transaction history.
- Customer and supplier management.
- Store settings for business identity and product behavior.
- SaaS billing module for Basic and Plus plan QRIS payment generation through Pakasir.
- Pakasir webhook endpoint for automated payment status updates.
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

## Billing and Pakasir QRIS

The billing module is available from Pengaturan > Billing, using this route:

```text
/billings
```

It creates a local billing record, calls Pakasir to create a QRIS transaction, stores the payment URL/payment metadata, and updates payment status from webhook callbacks or manual status checks.

Available SaaS plan presets:

```text
Basic Plan   Rp149.000
Plus Plan    Rp249.000
```

Set these variables in `.env`:

```env
PAKASIR_PROJECT=your-pakasir-project
PAKASIR_API_KEY=your-pakasir-api-key
PAKASIR_BASE_URL=https://app.pakasir.com/api
PAKASIR_TIMEOUT=30
```

Register this webhook URL in Pakasir:

```text
https://your-domain.com/pakasir/webhook
```

For local webhook testing, expose your local server with a tunnel such as ngrok or Cloudflare Tunnel, then use the public tunnel URL:

```text
https://your-tunnel-url/pakasir/webhook
```

Important billing routes:

```text
GET  /billings
POST /billings
POST /billings/{billing}/refresh
POST /pakasir/webhook
```

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

The tests cover authentication, dashboard rendering, product and variant workflows, inventory movement, categories, suppliers, customers, settings, billing creation, and Pakasir webhook payment completion.

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
app/Services             External service clients, including Pakasir
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

Make sure `/pakasir/webhook` is reachable publicly so Pakasir can automate payment status updates.
