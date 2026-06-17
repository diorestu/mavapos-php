<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('pages.settings.index', [
            'title' => 'Pengaturan',
            'setting' => StoreSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'business_type' => ['nullable', 'string', 'max:40'],
            'currency' => ['nullable', 'string', 'max:10'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'website' => ['nullable', 'url', 'max:160'],
            'instagram' => ['nullable', 'string', 'max:120'],
            'facebook' => ['nullable', 'string', 'max:120'],
            'tiktok' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'operational_hours' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'product_categories' => ['nullable', 'string', 'max:1000'],
            'product_units' => ['nullable', 'string', 'max:500'],
            'product_brands' => ['nullable', 'string', 'max:1000'],
            'product_variants' => ['nullable', 'string', 'max:500'],
            'product_modifiers' => ['nullable', 'string', 'max:1000'],
            'sku_mode' => ['nullable', 'in:auto,manual'],
            'spicy_levels' => ['nullable', 'string', 'max:500'],
            'toppings' => ['nullable', 'string', 'max:1000'],
            'size_options' => ['nullable', 'string', 'max:500'],
        ]);

        unset($validated['logo']);

        $setting = StoreSetting::current();

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('store-logos', 'public');
        }

        foreach ($this->booleanFields() as $field) {
            $validated[$field] = $request->boolean($field);
        }

        $validated['business_type'] = $validated['business_type'] ?? 'retail';
        $validated['currency'] = $validated['currency'] ?? 'IDR';
        $validated['sku_mode'] = $validated['sku_mode'] ?? 'manual';

        $setting->update($validated);

        return redirect()
            ->route('settings')
            ->with('status', 'Pengaturan toko berhasil disimpan.');
    }

    private function booleanFields(): array
    {
        return [
            'barcode_enabled',
            'selling_price_enabled',
            'cost_price_enabled',
            'product_status_enabled',
            'cashier_favorite_enabled',
            'taxable_default',
            'discountable_default',
            'kitchen_notes_enabled',
            'dine_in_takeaway_enabled',
            'serving_time_enabled',
        ];
    }
}
