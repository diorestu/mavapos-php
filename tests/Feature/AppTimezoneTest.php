<?php

use App\Support\LocalTime;
use Carbon\Carbon;

test('server tetap UTC dan frontend memformat waktu ke UTC+8', function () {
    $utcTime = Carbon::parse('2026-07-22 00:00:00', 'UTC');

    expect(config('app.timezone'))->toBe('UTC')
        ->and(LocalTime::format($utcTime))->toBe('22 Jul 2026 08:00');
});
