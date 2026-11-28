<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityServiceHours
{
    public const SETTING = 'community-service-hours';

    public static function limits(): array
    {
        $stored = DB::table('settings')->where('key', 'staff.'.self::SETTING)->value('value');

        return json_decode($stored ?? '[]', true) ?? [];
    }

    public static function validate(string $severity, int $hours): void
    {
        $range = self::limits()[$severity] ?? null;
        if ($range && ($hours < $range['min'] || $hours > $range['max'])) {
            throw ValidationException::withMessages([
                'required_hours' => "{$severity} violations require {$range['min']} to {$range['max']} community service hours.",
            ]);
        }
    }
}
