<?php

namespace App\Services\Partner;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ApiInput
{
    public static function timestamp(string $value, string $field): Carbon
    {
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value);
        if (! $valid) {
            throw ValidationException::withMessages([$field => 'Use ISO 8601 with seconds and offset, e.g. 2026-11-13T00:00:00+08:00.']);
        }
        try {
            $format = str_ends_with($value, 'Z') ? 'Y-m-d\TH:i:s\Z' : 'Y-m-d\TH:i:sP';
            $date = Carbon::createFromFormat('!'.$format, $value, 'UTC');
            if ($date->format($format) !== $value) {
                throw new \InvalidArgumentException;
            }

            return $date->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Invalid timestamp.']);
        }
    }

    public static function pagination(Request $request): array
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1|max:100000', 'per_page' => 'sometimes|integer|min:1|max:100']);

        return [(int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 50)];
    }

    public static function window(Request $request): array
    {
        $data = $request->validate(['outlet_id' => 'required|integer|min:1', 'from' => 'required|string|max:40', 'to' => 'required|string|max:40', 'updated_since' => 'sometimes|string|max:40', 'status' => 'sometimes|in:completed,voided,partially_refunded,refunded']);
        $from = self::timestamp($data['from'], 'from');
        $to = self::timestamp($data['to'], 'to');
        if ($to <= $from || $from->diffInSeconds($to) > 31 * 86400) {
            throw ValidationException::withMessages(['to' => 'Must be after from and at most 31 days later.']);
        }

        return [$data, $from, $to, isset($data['updated_since']) ? self::timestamp($data['updated_since'], 'updated_since') : null];
    }
}
