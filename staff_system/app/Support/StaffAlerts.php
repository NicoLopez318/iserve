<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

final class StaffAlerts
{
    public static function role(string $role, string $title, string $path, string $category = 'submissions', ?int $except = null): void
    {
        User::where('role', $role)->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->each(fn (User $user) => self::account($user, $title, $path, $category));
    }

    public static function account(User $user, string $title, string $path, string $category = 'feedback', string $message = ''): void
    {
        $preferences = $user->preferences ?? [];
        $alerts = $preferences['alerts'] ?? [];
        $fallback = match ($category) {
            'report_returned', 'report_acknowledged' => $alerts['feedback'] ?? true,
            'proposals', 'event_updates', 'organization_reports' => $alerts['submissions'] ?? true,
            'event_resubmitted' => $alerts['event_updates'] ?? $alerts['resubmitted'] ?? true,
            default => true,
        };
        if (!($alerts[$category] ?? $fallback)) {
            return;
        }

        $staff = $user->isStaff();
        $url = $staff ? config('portal.staff_url') : config('portal.student_url');
        $url = rtrim($url, '/').$path;

        if ($preferences['in_app'] ?? true) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $staff ? 'staff-workflow' : 'workflow',
                'data' => ['title' => $title, 'url' => $url, 'message' => $message],
            ]);
        }

        if ($preferences['email'] ?? false) {
            TransactionEmail::sendAfterCommit($user, $title, trim($message."\n\n".$url), ['category' => $category]);
        }
    }
}
