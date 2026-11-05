<?php

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function apiDeletionBody(array $changes = []): array
{
    return array_replace(['verification' => 'password', 'password' => 'password', 'confirmation' => 'HAPUS AKUN', 'understand' => true], $changes);
}

test('bearer token can delete its own account at each documented account endpoint', function (string $method, string $path) {
    $user = User::factory()->create(['password' => 'password', 'trial_ends_at' => now()->subDay()]);
    $other = User::factory()->create();
    $token = $user->createToken('mobile-pos', ['mobile:pos'])->plainTextToken;
    $user->createToken('another-device', ['mobile:pos']);
    $response = $this->withHeader('Authorization', 'Bearer '.$token)->json($method, $path, apiDeletionBody(['user_id' => $other->id]));
    $response->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('message', 'Akun Anda berhasil dihapus.');
    $deleted = User::withTrashed()->findOrFail($user->id);
    expect($deleted->trashed())->toBeTrue();
    expect($deleted->google_id)->toBeNull();
    expect($deleted->tokens()->count())->toBe(0);
    expect(Hash::check('password', $deleted->password))->toBeFalse();
    expect($other->fresh()->trashed())->toBeFalse();
})->with([
    ['DELETE', '/account/delete'], ['POST', '/account/delete'],
    ['DELETE', '/api/mobile/v1/account/delete'], ['POST', '/api/mobile/v1/account/delete'],
]);

test('account API errors are JSON without an Accept header and do not redirect', function (string $path) {
    $this->delete($path)->assertUnauthorized()->assertHeader('content-type', 'application/json')->assertJsonStructure(['message']);
})->with(['/account/delete', '/api/mobile/v1/account/delete']);

test('cookie session alone cannot invoke the stateless account deletion alias', function () {
    $user = User::factory()->create(['password' => 'password']);
    $this->actingAs($user)->deleteJson('/account/delete', apiDeletionBody())->assertUnauthorized();
    expect($user->fresh()->trashed())->toBeFalse();
});

test('API self deletion rejects incorrect password and confirmation without affecting tokens', function (array $changes, string $field) {
    $user = User::factory()->create(['password' => 'password']);
    $token = $user->createToken('mobile-pos')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/api/mobile/v1/account/delete', apiDeletionBody($changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($user->fresh()->trashed())->toBeFalse();
    expect($user->tokens()->count())->toBe(1);
})->with([[['password' => 'wrong'], 'password'], [['confirmation' => 'DELETE'], 'confirmation'], [['understand' => false], 'understand']]);

test('API self deletion rejects an active shift and preserves the account', function () {
    $user = User::factory()->create(['password' => 'password']);
    $token = $user->createToken('mobile-pos')->plainTextToken;
    $this->actingAs($user);
    $branch = app(BranchContext::class)->active();
    CashierShift::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'opened_at' => now()]);
    auth()->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/account/delete', apiDeletionBody())->assertUnprocessable()->assertJsonValidationErrors('account');
    expect($user->fresh()->trashed())->toBeFalse();
});

test('expired subscription permits mobile account info and logout', function () {
    $user = User::factory()->create(['trial_ends_at' => now()->subDay()]);
    $token = $user->createToken('mobile-pos', ['mobile:pos'])->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/mobile/v1/me')->assertOk()->assertJsonPath('account_deletion.path', '/account/delete');
    $this->postJson('/api/mobile/v1/logout')->assertOk();
    expect($user->tokens()->count())->toBe(0);
    auth()->forgetGuards();
    $this->getJson('/api/mobile/v1/me')->assertUnauthorized();
});

test('mobile account info preserves selected branch validation', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $other = User::factory()->create(['role' => 'owner']);
    Branch::query()->create(['user_id' => $owner->id, 'name' => 'First', 'code' => 'ME-FIRST', 'is_active' => true]);
    $second = Branch::query()->create(['user_id' => $owner->id, 'name' => 'Second', 'code' => 'ME-SECOND', 'is_active' => true]);
    $foreign = Branch::query()->create(['user_id' => $other->id, 'name' => 'Foreign', 'code' => 'ME-FOREIGN', 'is_active' => true]);
    $token = $owner->createToken('mobile-pos')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/mobile/v1/me?branch_id='.$second->id)
        ->assertOk()->assertJsonPath('branch.id', $second->id);
    $this->getJson('/api/mobile/v1/me?branch_id='.$foreign->id)->assertNotFound();
});

test('expired subscriber can close their active shift and then delete their account', function () {
    $user = User::factory()->create(['password' => 'password', 'trial_ends_at' => now()->subDay()]);
    $branch = Branch::query()->create(['user_id' => $user->id, 'name' => 'Close Branch', 'code' => 'DELETE-CLOSE', 'is_active' => true]);
    $shift = CashierShift::query()->create(['user_id' => $user->id, 'branch_id' => $branch->id, 'opened_at' => now()->subHour()]);
    $token = $user->createToken('mobile-pos', ['mobile:pos'])->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/account/delete', apiDeletionBody())->assertUnprocessable();
    $this->postJson('/api/mobile/v1/shifts/close', ['branch_id' => $branch->id])->assertOk();
    expect($shift->fresh()->closed_at)->not->toBeNull();
    $this->deleteJson('/account/delete', apiDeletionBody())->assertOk()->assertJsonPath('deleted', true);
    $this->assertSoftDeleted('users', ['id' => $user->id]);
});
