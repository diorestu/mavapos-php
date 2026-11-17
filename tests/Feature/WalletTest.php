<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\GatewayPayment;
use App\Models\PosSale;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WalletWithdrawal;
use App\Services\SelfAccountDeletionService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function walletFixture(string $environment = 'sandbox'): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $admin = User::factory()->create(['role' => 'superadmin']);
    $branch = Branch::create(['user_id' => $owner->id, 'name' => 'Wallet Outlet', 'code' => 'WALLET-'.Str::random(6)]);
    $shift = CashierShift::create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $sale = PosSale::create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'cashier_shift_id' => $shift->id, 'invoice_number' => 'WALLET-'.Str::uuid(), 'payment_method' => 'midtrans', 'payment_status' => 'paid', 'subtotal' => 100000, 'total' => 100000, 'sold_at' => now()]);
    $payment = GatewayPayment::create(['owner_id' => $owner->id, 'branch_id' => $branch->id, 'actor_id' => $owner->id, 'pos_sale_id' => $sale->id, 'environment' => $environment, 'order_id' => 'POS-PG-'.Str::uuid(), 'client_request_id' => (string) Str::uuid(), 'request_hash' => 'test-hash', 'amount' => 100000, 'status' => 'settlement', 'creation_status' => 'ready', 'settled_at' => now(), 'expires_at' => now()->addMinutes(15)]);

    return compact('owner', 'admin', 'branch', 'sale', 'payment');
}

test('settled POS receipts enter only their merchant and environment wallet once', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->settle($f['payment']);
    $account = $service->account($f['owner']->id, 'sandbox');
    expect($service->balances($account)['unreconciled'])->toBe(100000)->and($service->balances($account)['available'])->toBe(0)
        ->and(WalletEntry::count())->toBe(1)->and($service->balances($service->account($f['owner']->id, 'production'))['unreconciled'])->toBe(0);
    $other = User::factory()->create(['role' => 'owner']);
    expect($service->balances($service->account($other->id, 'sandbox'))['unreconciled'])->toBe(0);
});

test('only a superadmin can reconcile actual provider fees and make net receipts available', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->reconcileFee($f['payment'], 2000, 'MIDTRANS-REPORT-1', $f['admin']);
    $service->reconcileFee($f['payment'], 2000, 'MIDTRANS-REPORT-1', $f['admin']);
    $balances = $service->balances($service->account($f['owner']->id, 'sandbox'));
    expect($balances['available'])->toBe(98000)->and($balances['unreconciled'])->toBe(0)->and($balances['fees'])->toBe(2000);
    expect(fn () => $service->reconcileFee($f['payment'], 1000, 'Changed', $f['owner']))->toThrow(HttpException::class);
});

test('duplicate and increasing remote refunds debit the available bucket exactly once', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->reconcileFee($f['payment'], 2000, 'REPORT', $f['admin']);
    foreach ([20000, 20000, 50000, 10000] as $amount) {
        $service->refund($f['payment'], $amount);
    }
    expect($service->balances($service->account($f['owner']->id, 'sandbox'))['available'])->toBe(48000)
        ->and($f['payment']->fresh()->refunded_amount)->toBe(50000);
});

test('refund before fee reconciliation reduces unallocated funds and preserves actual fee expense', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->refund($f['payment'], 100000);
    $service->reconcileFee($f['payment'], 1000, 'FULL-REFUND-FEE', $f['admin']);
    $balances = $service->balances($service->account($f['owner']->id, 'sandbox'));
    expect($balances['available'])->toBe(-1000)->and($balances['unreconciled'])->toBe(0);
});

test('withdrawal requests reserve funds once and cannot exceed available net balance', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->reconcileFee($f['payment'], 2000, 'REPORT', $f['admin']);
    $account = $service->account($f['owner']->id, 'sandbox');
    $data = ['request_id' => (string) Str::uuid(), 'amount' => 50000, 'bank_name' => 'Bank Uji', 'account_number' => '001234567890', 'account_holder' => 'Pemilik Uji'];
    $a = $service->requestWithdrawal($account, $f['owner'], $data);
    $b = $service->requestWithdrawal($account, $f['owner'], $data);
    expect($a->id)->toBe($b->id)->and($service->balances($account)['available'])->toBe(48000)->and($service->balances($account)['reserved'])->toBe(50000);
    expect(fn () => $service->requestWithdrawal($account, $f['owner'], array_replace($data, ['request_id' => (string) Str::uuid(), 'amount' => 49000])))->toThrow(ValidationException::class);
    expect(fn () => $service->requestWithdrawal($account, $f['owner'], array_replace($data, ['amount' => 10000])))->toThrow(HttpException::class);
    expect($a->getRawOriginal('account_number'))->not->toBe('001234567890');
});

test('manual withdrawal processing records a transfer only once and releases rejected requests', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->reconcileFee($f['payment'], 0, 'REPORT', $f['admin']);
    $account = $service->account($f['owner']->id, 'sandbox');
    $data = ['request_id' => (string) Str::uuid(), 'amount' => 40000, 'bank_name' => 'Bank Uji', 'account_number' => '001234567890', 'account_holder' => 'Pemilik Uji'];
    $withdrawal = $service->requestWithdrawal($account, $f['owner'], $data);
    $service->processWithdrawal($withdrawal, $f['admin'], 'processing');
    $service->processWithdrawal($withdrawal, $f['admin'], 'completed', 'BANK-TRANSFER-1');
    $service->processWithdrawal($withdrawal, $f['admin'], 'completed', 'BANK-TRANSFER-1');
    $second = $service->requestWithdrawal($account, $f['owner'], array_replace($data, ['request_id' => (string) Str::uuid()]));
    $service->processWithdrawal($second, $f['admin'], 'rejected', 'Belum ditransfer');
    $balances = $service->balances($account);
    expect($balances['available'])->toBe(60000)->and($balances['reserved'])->toBe(0)->and($balances['paid_out'])->toBe(40000);
});

test('wallet ledger entries cannot be edited or deleted through the model', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $entry = WalletEntry::firstOrFail();
    expect(fn () => $entry->update(['amount' => 999999]))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});

test('merchant wallet views and withdrawal actions cannot access another merchant', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->reconcileFee($f['payment'], 2000, 'REPORT', $f['admin']);
    $this->actingAs($f['owner'])->get('/wallet?environment=sandbox')->assertOk()->assertSee('Rp98.000');
    $other = User::factory()->create(['role' => 'owner']);
    $this->actingAs($other)->get('/wallet?environment=sandbox')->assertOk()->assertDontSee('Rp98.000');
    $this->get('/wallet?environment=sandbox&branch_id='.$f['branch']->id)->assertNotFound();
    $this->get('/superadmin/wallets')->assertForbidden();
    $this->actingAs($f['admin'])->get('/superadmin/wallets?environment=sandbox')->assertOk()->assertSee($f['owner']->name);
});

test('fee and payout admin endpoints require explicit confirmations and role authorization', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $url = '/superadmin/gateway-payments/'.$f['payment']->id.'/reconcile';
    $this->actingAs($f['owner'])->postJson($url, ['fee_amount' => 1000, 'reference' => 'REPORT', 'funds_available' => true])->assertForbidden();
    $this->actingAs($f['admin'])->postJson($url, ['fee_amount' => 1000, 'reference' => 'REPORT'])->assertUnprocessable();
    $this->post($url, ['fee_amount' => 1000, 'reference' => 'REPORT', 'funds_available' => true])->assertRedirect();
    $this->actingAs($f['owner'])->post('/wallet/withdrawals', ['environment' => 'sandbox', 'request_id' => (string) Str::uuid(), 'amount' => 50000, 'bank_name' => 'Bank Uji', 'account_number' => '001234567890', 'account_holder' => 'Pemilik Uji'])->assertRedirect();
    $w = WalletWithdrawal::firstOrFail();
    $this->get('/wallet?environment=sandbox')->assertOk()->assertDontSee('001234567890')->assertSee('7890');
    $this->actingAs($f['admin'])->patch('/superadmin/wallet-withdrawals/'.$w->id, ['action' => 'processing'])->assertRedirect()->assertSessionHasNoErrors();
    $this->patchJson('/superadmin/wallet-withdrawals/'.$w->id, ['action' => 'completed', 'reference' => 'BANK-REF'])->assertUnprocessable();
    $this->patch('/superadmin/wallet-withdrawals/'.$w->id, ['action' => 'completed', 'reference' => 'BANK-REF', 'transferred_confirmation' => true])->assertRedirect()->assertSessionHasNoErrors();
    expect($w->fresh()->status)->toBe('completed');
});

test('a refund while a withdrawal is reserved blocks processing and releasing it restores the correct remainder', function () {
    $f = walletFixture();
    $service = app(WalletService::class);
    $service->settle($f['payment']);
    $service->reconcileFee($f['payment'], 0, 'REPORT', $f['admin']);
    $account = $service->account($f['owner']->id, 'sandbox');
    $w = $service->requestWithdrawal($account, $f['owner'], ['request_id' => (string) Str::uuid(), 'amount' => 80000, 'bank_name' => 'Bank Uji', 'account_number' => '001234567890', 'account_holder' => 'Pemilik Uji']);
    $service->refund($f['payment'], 100000);
    expect($service->balances($account)['available'])->toBe(-80000);
    expect(fn () => $service->processWithdrawal($w, $f['admin'], 'processing'))->toThrow(HttpException::class);
    $service->processWithdrawal($w, $f['admin'], 'rejected', 'Dana belum ditransfer');
    expect($service->balances($account)['available'])->toBe(0)->and($service->balances($account)['reserved'])->toBe(0);
});

test('gateway merchant identity original outlet and amount cannot be changed after creation', function () {
    $f = walletFixture();
    expect(fn () => $f['payment']->update(['amount' => 1]))->toThrow(LogicException::class);
    expect(fn () => $f['payment']->update(['owner_id' => $f['admin']->id]))->toThrow(LogicException::class);
});

test('an owner cannot abandon production wallet funds by deleting the account', function () {
    $f = walletFixture('production');
    $f['sale']->shift->update(['closed_at' => now()]);
    app(WalletService::class)->settle($f['payment']);
    expect(fn () => app(SelfAccountDeletionService::class)->delete($f['owner'], ['verification' => 'password', 'password' => 'password']))->toThrow(ValidationException::class, 'Wallet');
    expect($f['owner']->fresh()->deleted_at)->toBeNull();
});
