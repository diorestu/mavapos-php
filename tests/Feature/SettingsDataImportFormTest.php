<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('form import SQL terpisah dari form pengaturan utama', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($owner)
        ->get(route('settings'))
        ->assertOk()
        ->assertSee('form="sql-import-form"', false);
});
