<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CommercialDemoDataService
{
    public function seed(User $owner, Branch $branch): array
    {
        if ($branch->user_id !== $owner->id) {
            throw new InvalidArgumentException('Cabang tidak dimiliki usaha yang dipilih.');
        }
        $existing = Product::withoutGlobalScopes()->where('user_id', $owner->id)->whereIn('sku', ['PSC40', 'PSC75'])->orderBy('id')->get();
        if ($existing->isEmpty()) {
            throw new InvalidArgumentException('Katalog pie susu PSC40/PSC75 tidak ditemukan pada usaha ini.');
        }
        foreach (['pie-40', 'pie-75', 'pie-10', 'pie-20', 'pie-gift'] as $image) {
            $source = public_path('images/demo/pie-susu-candra/'.$image.'.webp');
            if (! is_file($source)) {
                throw new InvalidArgumentException('Foto demo belum tersedia: '.$image);
            }
            Storage::disk('public')->put('products/demo-commercial/'.$image.'.webp', file_get_contents($source));
        }

        return DB::transaction(function () use ($owner, $branch, $existing): array {
            $now = now('Asia/Makassar');
            $prefix = 'DEMO-PSC-'.$owner->id.'-';
            $products = $existing;
            foreach ($products as $product) {
                $product->update(['image_path' => 'products/demo-commercial/'.($product->sku === 'PSC40' ? 'pie-40' : 'pie-75').'.webp']);
            }
            foreach ([
                ['10', 'Pie Susu Original isi 10 pcs (Demo)', 15000, 6000, 8, 15, 'pie-10'],
                ['20', 'Pie Susu Original isi 20 pcs (Demo)', 28000, 12000, 145, 25, 'pie-20'],
                ['GIFT', 'Gift Box Pie Susu isi 24 pcs (Demo)', 45000, 21000, 21, 30, 'pie-gift'],
            ] as [$sku, $name, $price, $cost, $stock, $minimum, $image]) {
                $product = Product::withoutGlobalScopes()->firstOrCreate(['user_id' => $owner->id, 'sku' => $prefix.$sku], [
                    'product_category_id' => $existing->first()->product_category_id, 'name' => $name,
                    'sell_price' => $price, 'buy_price' => $cost, 'stock' => $stock, 'min_stock' => $minimum,
                    'stock_mode' => 'inventory', 'image_path' => 'products/demo-commercial/'.$image.'.webp',
                    'description' => 'DEMO KOMERSIAL — produk dan foto ilustrasi untuk presentasi MavaPOS.',
                ]);
                $products->push($product);
            }
            foreach ($products as $product) {
                if (! str_starts_with($product->sku, $prefix)) {
                    continue;
                }
                BranchInventory::query()->firstOrCreate([
                    'branch_id' => $branch->id, 'product_id' => $product->id, 'product_variant_id' => null,
                ], ['stock' => $product->stock, 'min_stock' => $product->min_stock]);
            }
            $customers = collect(range(1, 20))->map(fn (int $number) => Customer::withoutGlobalScopes()->firstOrCreate([
                'user_id' => $owner->id, 'code' => $prefix.'C'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            ], ['name' => 'Pelanggan Demo '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'email' => 'demo'.$owner->id.'.'.$number.'@example.invalid', 'status' => 'aktif']));
            $created = 0;
            $shifts = [];
            $methods = ['cash', 'qris', 'qris', 'online_merchant', 'cash', 'card', 'online_merchant', 'split', 'qris', 'cash'];
            $monthlyCounts = [48, 73, 66, 94, 88, 119, 151, 133, 174, 163, 182, 207];
            $productWeights = [0, 0, 0, 0, 1, 1, 1, 2, 3, 4];
            for ($month = 1; $month <= $now->month; $month++) {
                $start = $now->copy()->startOfYear()->month($month);
                $days = $month === $now->month ? max(1, $now->day - 1) : $start->daysInMonth;
                for ($index = 0; $index < $monthlyCounts[$month - 1]; $index++) {
                    $invoice = $prefix.$now->year.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
                    if (PosSale::query()->where('invoice_number', $invoice)->exists()) {
                        continue;
                    }
                    $day = $month === $now->month && $index < 8 ? $now->day : 1 + (($index * 7 + $month) % $days);
                    $soldAt = $start->copy()->day($day)->setTime(8 + ($index % 12), ($index * 11) % 60);
                    if ($soldAt->greaterThan($now)) {
                        $soldAt = $now->copy()->subMinutes(1 + ($index % 45));
                    }
                    $key = $soldAt->toDateString();
                    $shift = $shifts[$key] ??= CashierShift::query()->firstOrCreate([
                        'user_id' => $owner->id, 'branch_id' => $branch->id, 'opening_note' => $prefix.$key,
                    ], [
                        'opened_at' => $soldAt->copy()->startOfDay()->utc(),
                        'closed_at' => $soldAt->copy()->endOfDay()->min($now)->utc(),
                        'opening_cash_amount' => 250000, 'closing_note' => 'DEMO KOMERSIAL — histori simulasi, bukan setoran kas operasional.',
                    ]);
                    $lines = collect();
                    $lineCount = $index % 3 === 0 ? 2 : 1;
                    for ($line = 0; $line < $lineCount; $line++) {
                        $productIndex = $productWeights[($index * 7 + $month + $line * 3) % count($productWeights)];
                        $product = $products[$productIndex % $products->count()];
                        $quantity = 1 + (($index + $line) % 3);
                        $lines->push(['product_id' => $product->id, 'item_type' => 'product', 'name' => $product->name, 'sku' => $product->sku, 'quantity' => $quantity, 'unit_price' => $product->sell_price, 'line_total' => $product->sell_price * $quantity]);
                    }
                    $subtotal = (int) $lines->sum('line_total');
                    $discount = $index % 7 === 0 ? (int) floor($subtotal * 0.05 / 100) * 100 : 0;
                    $total = $subtotal - $discount;
                    $method = $methods[($index + $month) % count($methods)];
                    $merchant = $method === 'online_merchant' ? array_keys(PosSale::ONLINE_MERCHANTS)[($index + $month) % 4] : null;
                    $paid = $method === 'online_merchant' ? $total - (int) floor($total * 0.18 / 100) * 100 : $total;
                    $sale = PosSale::query()->create([
                        'cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'user_id' => $owner->id,
                        'customer_id' => $customers[$index % $customers->count()]->id,
                        'invoice_number' => $invoice, 'payment_method' => $method, 'online_merchant' => $merchant,
                        'buyer_nationality' => $index % 4 === 0 ? 'foreigner' : 'local',
                        'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'paid_amount' => $paid,
                        'change_amount' => 0, 'sold_at' => $soldAt->copy()->utc(),
                    ]);
                    $sale->items()->createMany($lines->all());
                    if ($method === 'split') {
                        $cash = (int) floor($total / 2);
                        $sale->payments()->createMany([['payment_method' => 'cash', 'amount' => $cash], ['payment_method' => 'qris', 'amount' => $total - $cash]]);
                    }
                    $created++;
                }
                foreach ([['Listrik dan air', 350000 + $month * 19000], ['Kemasan dan perlengkapan', 220000 + ($month % 4) * 85000], ['Pengiriman dan operasional', 180000 + ($month % 3) * 70000]] as $index => [$title, $amount]) {
                    Expense::withoutGlobalScopes()->firstOrCreate(['user_id' => $owner->id, 'expense_number' => $prefix.'EXP-'.$now->year.'-'.$month.'-'.$index], [
                        'branch_id' => $branch->id, 'type' => 'operational', 'category' => 'Demo komersial', 'title' => $title.' (Demo)',
                        'amount' => $amount, 'note' => 'DEMO KOMERSIAL — simulasi untuk presentasi.', 'spent_at' => $start->copy()->addDays(1)->min($now)->utc(),
                    ]);
                }
            }
            foreach ($shifts as $shift) {
                app(CashierShiftSummaryService::class)->refresh($shift);
            }
            $this->stockHistory($products, $owner, $branch, $prefix, $now->year);

            return ['sales' => $created, 'products' => $products->count(), 'customers' => $customers->count()];
        });
    }

    private function stockHistory($products, User $owner, Branch $branch, string $prefix, int $year): void
    {
        foreach ($products as $product) {
            $lines = $product->hasMany(PosSaleItem::class)->whereHas('sale', fn ($query) => $query->where('branch_id', $branch->id)->where('invoice_number', 'like', $prefix.$year.'%'))->with('sale')->get()->sortBy(fn ($line) => $line->sale->sold_at);
            if ($lines->isEmpty()) {
                continue;
            }
            $defaultBranchId = Branch::withoutGlobalScopes()->where('user_id', $owner->id)->where('is_active', true)->orderBy('id')->value('id');
            $current = BranchInventory::query()->where('branch_id', $branch->id)->where('product_id', $product->id)->whereNull('product_variant_id')->value('stock')
                ?? ($defaultBranchId === $branch->id ? $product->stock : 0);
            $balance = $current + $lines->sum('quantity');
            StockMovement::query()->updateOrCreate(['reference' => $prefix.'OPEN-'.$year.'-'.$product->id], [
                'branch_id' => $branch->id, 'created_by_user_id' => $owner->id, 'product_id' => $product->id,
                'type' => 'in', 'quantity' => $lines->sum('quantity'), 'stock_before' => $current, 'stock_after' => $balance,
                'note' => 'DEMO KOMERSIAL — saldo awal histori simulasi; stok operasional tetap.', 'occurred_at' => $lines->first()->sale->sold_at->copy()->subHour(),
            ]);
            foreach ($lines as $line) {
                StockMovement::query()->updateOrCreate(['branch_id' => $branch->id, 'product_id' => $product->id, 'reference' => $line->sale->invoice_number, 'type' => 'out'], [
                    'created_by_user_id' => $owner->id, 'quantity' => $line->quantity, 'stock_before' => $balance, 'stock_after' => $balance - $line->quantity,
                    'note' => 'DEMO KOMERSIAL — penjualan simulasi.', 'occurred_at' => $line->sale->sold_at,
                ]);
                $balance -= $line->quantity;
            }
        }
    }
}
