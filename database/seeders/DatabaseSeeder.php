<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'minuman',
                'name' => 'Minuman',
                'product_count' => 24,
                'status' => 'aktif',
                'description' => 'Kategori untuk produk minuman siap jual.',
            ],
            [
                'code' => 'makanan',
                'name' => 'Makanan',
                'product_count' => 18,
                'status' => 'aktif',
                'description' => 'Kategori untuk makanan ringan dan makanan kemasan.',
            ],
            [
                'code' => 'sembako',
                'name' => 'Sembako',
                'product_count' => 12,
                'status' => 'aktif',
                'description' => 'Kategori kebutuhan pokok harian.',
            ],
            [
                'code' => 'perawatan',
                'name' => 'Perawatan',
                'product_count' => 9,
                'status' => 'aktif',
                'description' => 'Kategori produk perawatan diri.',
            ],
            [
                'code' => 'rumah-tangga',
                'name' => 'Rumah Tangga',
                'product_count' => 7,
                'status' => 'nonaktif',
                'description' => 'Kategori perlengkapan rumah tangga.',
            ],
        ];

        $categoryIds = [];

        foreach ($categories as $category) {
            $categoryIds[$category['code']] = ProductCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category,
            )->id;
        }

        $products = [
            [
                'sku' => 'SKU-001',
                'name' => 'Kopi Susu Aren 250ml',
                'category' => 'minuman',
                'stock' => 128,
                'sell_price' => 18000,
            ],
            [
                'sku' => 'SKU-014',
                'name' => 'Roti Cokelat Premium',
                'category' => 'makanan',
                'stock' => 18,
                'min_stock' => 20,
                'sell_price' => 12500,
            ],
            [
                'sku' => 'SKU-029',
                'name' => 'Sabun Cair Lavender',
                'category' => 'perawatan',
                'stock' => 72,
                'sell_price' => 24000,
            ],
            [
                'sku' => 'SKU-037',
                'name' => 'Beras Premium 5kg',
                'category' => 'sembako',
                'stock' => 0,
                'sell_price' => 74000,
            ],
            [
                'sku' => 'SKU-052',
                'name' => 'Tisu Wajah 250 Sheet',
                'category' => 'rumah-tangga',
                'stock' => 214,
                'sell_price' => 16000,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'product_category_id' => $categoryIds[$product['category']] ?? null,
                    'name' => $product['name'],
                    'buy_price' => $product['buy_price'] ?? 0,
                    'sell_price' => $product['sell_price'],
                    'stock' => $product['stock'],
                    'min_stock' => $product['min_stock'] ?? 0,
                    'description' => $product['description'] ?? null,
                ],
            );
        }

        $suppliers = [
            [
                'code' => 'SUP-001',
                'name' => 'PT Kopi Nusantara',
                'phone' => '081111110001',
                'email' => 'kopi@example.com',
                'status' => 'aktif',
                'address' => 'Jl. Gudang Kopi No. 1',
            ],
            [
                'code' => 'SUP-002',
                'name' => 'CV Roti Makmur',
                'phone' => '081111110002',
                'email' => 'roti@example.com',
                'status' => 'aktif',
                'address' => 'Jl. Industri Roti No. 2',
            ],
            [
                'code' => 'SUP-003',
                'name' => 'UD Sembako Jaya',
                'phone' => '081111110003',
                'email' => 'sembako@example.com',
                'status' => 'nonaktif',
                'address' => 'Jl. Pasar Induk No. 3',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['code' => $supplier['code']],
                $supplier,
            );
        }

        $customers = [
            [
                'code' => 'CUST-001',
                'name' => 'Budi Santoso',
                'phone' => '082222220001',
                'email' => 'budi@example.com',
                'status' => 'aktif',
                'address' => 'Jl. Loyalti No. 1',
            ],
            [
                'code' => 'CUST-002',
                'name' => 'Siti Aminah',
                'phone' => '082222220002',
                'email' => 'siti@example.com',
                'status' => 'aktif',
                'address' => 'Jl. Pelanggan Tetap No. 2',
            ],
            [
                'code' => 'CUST-003',
                'name' => 'Andi Pratama',
                'phone' => '082222220003',
                'email' => 'andi@example.com',
                'status' => 'nonaktif',
                'address' => 'Jl. Member Lama No. 3',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                ['code' => $customer['code']],
                $customer,
            );
        }

        StoreSetting::query()->firstOrCreate([], StoreSetting::defaults());

        $owner = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'owner',
                'trial_ends_at' => now()->addDays(14),
            ],
        );

        foreach (['products', 'product_categories', 'suppliers', 'customers', 'expenses', 'raw_materials', 'store_settings', 'branches'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                \Illuminate\Support\Facades\DB::table($table)->whereNull('user_id')->update(['user_id' => $owner->id]);
            }
        }
    }
}
