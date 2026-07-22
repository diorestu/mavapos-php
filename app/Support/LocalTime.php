<?php

namespace App\Support;

use DateTimeInterface;

class LocalTime
{
    public const TIMEZONE = 'Asia/Makassar';

    public static function format(?DateTimeInterface $date, string $format = 'd M Y H:i', string $fallback = '-'): string
    {
        return $date ? $date->setTimezone(new \DateTimeZone(self::TIMEZONE))->format($format) : $fallback;
    }
}
