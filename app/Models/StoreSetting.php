<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'store_name',
        'tagline',
        'logo_path',
        'legal_name',
        'owner_name',
        'business_type',
        'currency',
        'address',
        'phone',
        'whatsapp',
        'email',
        'website',
        'instagram',
        'facebook',
        'tiktok',
        'tax_number',
        'operational_hours',
        'notes',
        'product_categories',
        'product_units',
        'product_brands',
        'product_variants',
        'product_modifiers',
        'sku_mode',
        'barcode_enabled',
        'selling_price_enabled',
        'cost_price_enabled',
        'product_status_enabled',
        'cashier_favorite_enabled',
        'taxable_default',
        'discountable_default',
        'spicy_levels',
        'toppings',
        'size_options',
        'kitchen_notes_enabled',
        'dine_in_takeaway_enabled',
        'serving_time_enabled',
        'receipt_footer_note',
        'receipt_paper_width',
        'receipt_show_logo',
        'receipt_show_store_address',
        'receipt_show_cashier',
        'printer_auto_print',
        'printer_close_after_print',
        'printer_connection_mode',
        'printer_bluetooth_service_uuid',
        'printer_bluetooth_characteristic_uuid',
    ];

    protected function casts(): array
    {
        return [
            'barcode_enabled' => 'boolean',
            'selling_price_enabled' => 'boolean',
            'cost_price_enabled' => 'boolean',
            'product_status_enabled' => 'boolean',
            'cashier_favorite_enabled' => 'boolean',
            'taxable_default' => 'boolean',
            'discountable_default' => 'boolean',
            'kitchen_notes_enabled' => 'boolean',
            'dine_in_takeaway_enabled' => 'boolean',
            'serving_time_enabled' => 'boolean',
            'receipt_show_logo' => 'boolean',
            'receipt_show_store_address' => 'boolean',
            'receipt_show_cashier' => 'boolean',
            'printer_auto_print' => 'boolean',
            'printer_close_after_print' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], self::defaults());
    }

    public static function defaults(): array
    {
        return [
            'store_name' => 'Mava Mart',
            'tagline' => 'Belanja hemat setiap hari',
            'logo_path' => null,
            'legal_name' => 'Mava Retail',
            'owner_name' => 'Admin Toko',
            'business_type' => 'retail',
            'currency' => 'IDR',
            'address' => 'Jl. Niaga No. 1, Makassar',
            'phone' => '0411-000000',
            'whatsapp' => '081234567890',
            'email' => 'halo@mavamart.test',
            'website' => 'https://mavamart.test',
            'instagram' => '@mavamart',
            'facebook' => 'Mava Mart',
            'tiktok' => '@mavamart',
            'tax_number' => null,
            'operational_hours' => 'Senin-Minggu 08.00-22.00',
            'notes' => 'Lengkapi identitas toko sesuai kebutuhan operasional.',
            'product_categories' => 'Minuman, Makanan, Sembako, Perawatan',
            'product_units' => 'pcs, box, kg, gram, liter, porsi',
            'product_brands' => 'Mava Mart',
            'product_variants' => 'Ukuran, Warna, Rasa',
            'product_modifiers' => 'Extra shot, Topping, Add-on',
            'sku_mode' => 'manual',
            'barcode_enabled' => true,
            'selling_price_enabled' => true,
            'cost_price_enabled' => true,
            'product_status_enabled' => true,
            'cashier_favorite_enabled' => false,
            'taxable_default' => false,
            'discountable_default' => true,
            'spicy_levels' => 'Tidak pedas, Sedang, Pedas',
            'toppings' => 'Keju, Boba, Telur',
            'size_options' => 'Regular, Large',
            'kitchen_notes_enabled' => true,
            'dine_in_takeaway_enabled' => true,
            'serving_time_enabled' => true,
            'receipt_footer_note' => 'Terima kasih sudah berbelanja.',
            'receipt_paper_width' => '58',
            'receipt_show_logo' => true,
            'receipt_show_store_address' => true,
            'receipt_show_cashier' => true,
            'printer_auto_print' => false,
            'printer_close_after_print' => false,
            'printer_connection_mode' => 'imin_inner_printer',
            'printer_bluetooth_service_uuid' => null,
            'printer_bluetooth_characteristic_uuid' => null,
        ];
    }
}
