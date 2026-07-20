<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('pengguna dapat mengunduh laporan dalam format excel', function () {
    $user = User::factory()->create(['role' => 'owner']);

    $response = $this->actingAs($user)
        ->get(route('reports.excel', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-20',
        ]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('laporan-2026-07-01-sampai-2026-07-20.xls')
        ->and($response->getContent())->toContain('Laporan MavaPOS')
        ->and($response->getContent())->toContain('Ringkasan');
});
