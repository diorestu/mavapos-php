<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cashiers and administrators can access printer diagnostics from their menu', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    expect(collect(MenuHelper::getMainNavItems())->pluck('path'))->toContain('/print-test');
    $this->get(route('print-test'))->assertOk()
        ->assertSee('Connect IMIN')
        ->assertSee('Print Test IMIN')
        ->assertSee('127.0.0.1:8081');

    if ($role === 'kasir') {
        $this->get(route('settings'))->assertForbidden();
    }
})->with(['kasir', 'owner', 'admin']);

test('other operational roles cannot access printer diagnostics', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    expect(collect(MenuHelper::getMainNavItems())->pluck('path'))->not->toContain('/print-test');
    $this->get(route('print-test'))->assertForbidden();
})->with(['gudang', 'runner']);

test('printer diagnostics require login', function () {
    $this->get(route('print-test'))->assertRedirect(route('signin'));
});
