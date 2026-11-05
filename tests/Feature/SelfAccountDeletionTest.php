<?php

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

function deletionInput(array $overrides = []): array
{
    return array_replace(['verification' => 'password', 'password' => 'password', 'confirmation' => 'HAPUS AKUN', 'understand' => '1'], $overrides);
}

test('self deletion anonymizes only the signed in account revokes tokens and preserves operational history', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['password' => 'password', 'google_id' => 'GOOGLE-SELF', 'profile_photo_path' => 'profile-photos/self.jpg']);
    Storage::disk('public')->put('profile-photos/self.jpg', 'photo');
    $originalEmail = $owner->email;
    $staff = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id]);
    $this->actingAs($owner);
    $branch = app(BranchContext::class)->active();
    $shift = CashierShift::query()->create(['user_id' => $owner->id, 'branch_id' => $branch->id, 'opened_at' => now()->subHour(), 'closed_at' => now()]);
    $sale = PosSale::query()->create(['cashier_shift_id' => $shift->id, 'branch_id' => $branch->id, 'user_id' => $owner->id, 'invoice_number' => 'DELETE-HISTORY', 'payment_method' => 'cash', 'subtotal' => 20000, 'total' => 20000, 'sold_at' => now()]);
    $owner->createToken('mobile-pos');

    $this->get(route('profile'))->assertOk()->assertSee('Hapus Akun');
    $this->delete('/profile', deletionInput(['user_id' => $staff->id]))->assertRedirect(route('signin'));
    $this->assertGuest();
    $deleted = User::withTrashed()->findOrFail($owner->id);
    expect($deleted->trashed())->toBeTrue();
    expect($deleted->name)->toBe('Akun Dihapus');
    expect($deleted->email)->not->toBe($originalEmail);
    expect($deleted->google_id)->toBeNull();
    expect($deleted->remember_token)->toBeNull();
    expect($deleted->profile_photo_path)->toBeNull();
    expect(Hash::check('password', $deleted->password))->toBeFalse();
    expect($deleted->tokens()->count())->toBe(0);
    expect($staff->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseHas('pos_sales', ['id' => $sale->id, 'total' => 20000]);
    $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    Storage::disk('public')->assertMissing('profile-photos/self.jpg');
    $this->post('/signin', ['email' => $originalEmail, 'password' => 'password'])->assertSessionHasErrors('email');
});

test('self deletion requires the right password exact confirmation and consent', function (array $invalid, string $field) {
    $user = User::factory()->create(['password' => 'password']);
    $this->actingAs($user)->from('/profile')->delete('/profile', deletionInput($invalid))
        ->assertRedirect('/profile')->assertSessionHasErrorsIn('accountDeletion', $field);
    expect($user->fresh()->trashed())->toBeFalse();
})->with([
    [['password' => 'wrong'], 'password'],
    [['confirmation' => 'DELETE'], 'confirmation'],
    [['understand' => '0'], 'understand'],
]);

test('an active shift must be closed before self deletion', function () {
    $user = User::factory()->create(['password' => 'password']);
    $this->actingAs($user);
    $branch = app(BranchContext::class)->active();
    CashierShift::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    $this->from('/profile')->delete('/profile', deletionInput())->assertSessionHasErrorsIn('accountDeletion', 'account');
    expect($user->fresh()->trashed())->toBeFalse();
});

test('guests cannot read profile or delete accounts', function () {
    $this->get('/profile')->assertRedirect(route('signin'));
    $this->delete('/profile', deletionInput())->assertRedirect(route('signin'));
});

test('google deletion verification is bound to the current account and expires', function () {
    $user = User::factory()->create(['google_id' => 'google-self']);
    $this->actingAs($user)->withSession(['account_deletion.google' => ['user_id' => $user->id, 'verified_at' => now()->subMinutes(6)->timestamp]])
        ->from('/profile')->delete('/profile', deletionInput(['verification' => 'google', 'password' => '']))
        ->assertSessionHasErrorsIn('accountDeletion', 'verification');
    $this->withSession(['account_deletion.google' => ['user_id' => $user->id + 1, 'verified_at' => now()->timestamp]])
        ->delete('/profile', deletionInput(['verification' => 'google', 'password' => '']))
        ->assertSessionHasErrorsIn('accountDeletion', 'verification');
    expect($user->fresh()->trashed())->toBeFalse();
});

test('a recently verified google account can delete itself without its generated password', function () {
    $user = User::factory()->create(['google_id' => 'google-self']);
    $this->actingAs($user)->withSession(['account_deletion.google' => ['user_id' => $user->id, 'verified_at' => now()->timestamp]])
        ->delete('/profile', deletionInput(['verification' => 'google', 'password' => '']))->assertRedirect(route('signin'));
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertGuest();
});

test('google callback verifies identity for deletion without switching the signed in account', function (string $googleId, bool $matches) {
    $user = User::factory()->create(['google_id' => 'google-self']);
    $google = (new GoogleUser)->map(['id' => $googleId, 'email' => 'external@example.com', 'name' => 'External']);
    Socialite::shouldReceive('driver')->with('google')->once()->andReturnSelf();
    Socialite::shouldReceive('user')->once()->andReturn($google);
    $response = $this->actingAs($user)->withSession(['account_deletion.oauth' => ['user_id' => $user->id, 'started_at' => now()->timestamp]])
        ->get(route('auth.google.callback'));
    $response->assertRedirect(route('profile'));
    $this->assertAuthenticatedAs($user);
    if ($matches) {
        $response->assertSessionHas('account_deletion.google.user_id', $user->id);
    } else {
        $response->assertSessionHasErrorsIn('accountDeletion', 'verification')->assertSessionMissing('account_deletion.google');
    }
    expect($user->fresh()->trashed())->toBeFalse();
})->with([['google-self', true], ['other-google-user', false]]);

test('a google verification redirect keeps the existing callback configuration and records current identity', function () {
    $user = User::factory()->create(['google_id' => 'google-self']);
    Socialite::shouldReceive('driver')->with('google')->once()->andReturnSelf();
    Socialite::shouldReceive('with')->with(['prompt' => 'select_account'])->once()->andReturnSelf();
    Socialite::shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    $this->actingAs($user)->get(route('profile.delete.google'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
        ->assertSessionHas('account_deletion.oauth.user_id', $user->id);
    $this->assertAuthenticatedAs($user);
});

test('google cancellation returns to profile with an error and does not delete or switch the account', function () {
    $user = User::factory()->create(['google_id' => 'google-self']);
    Socialite::shouldReceive('driver')->with('google')->once()->andReturnSelf();
    Socialite::shouldReceive('user')->once()->andThrow(new RuntimeException('Access denied'));
    $this->actingAs($user)->withSession(['account_deletion.oauth' => ['user_id' => $user->id, 'started_at' => now()->timestamp]])
        ->get(route('auth.google.callback'))->assertRedirect(route('profile'))
        ->assertSessionHasErrorsIn('accountDeletion', 'verification')->assertSessionMissing('account_deletion.google');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->trashed())->toBeFalse();
});

test('normal google sign in still logs in its matching account', function () {
    $user = User::factory()->create(['google_id' => 'google-login']);
    $google = (new GoogleUser)->map(['id' => 'google-login', 'email' => $user->email, 'name' => $user->name]);
    Socialite::shouldReceive('driver')->with('google')->once()->andReturnSelf();
    Socialite::shouldReceive('user')->once()->andReturn($google);
    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('self deletion removes all database sessions belonging only to the account', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create(['password' => 'password']);
    $other = User::factory()->create();
    foreach ([['own-one', $user->id], ['own-two', $user->id], ['other-account', $other->id]] as [$id, $userId]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
    }
    $this->actingAs($user)->delete('/profile', deletionInput())->assertRedirect(route('signin'));
    $this->assertDatabaseMissing('sessions', ['id' => 'own-one']);
    $this->assertDatabaseMissing('sessions', ['id' => 'own-two']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-account']);
});

test('staff can delete only their own account without an active subscription', function () {
    $owner = User::factory()->create(['trial_ends_at' => now()->subDay()]);
    $cashier = User::factory()->create(['role' => 'kasir', 'tenant_owner_id' => $owner->id, 'trial_ends_at' => now()->subDay(), 'password' => 'password']);
    $this->actingAs($cashier)->get('/profile')->assertOk()->assertSee('Hapus Akun');
    $this->delete('/profile', deletionInput())->assertRedirect(route('signin'));
    $this->assertSoftDeleted('users', ['id' => $cashier->id]);
    expect($owner->fresh()->trashed())->toBeFalse();
});
