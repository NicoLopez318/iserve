<?php

use App\Models\{Report, User};
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\{Artisan, DB, Schedule};

Artisan::command('notifications:overdue-reports', function () {
    $recipients = User::whereIn('role', ['assistant', 'admin'])->get()->groupBy('role');
    Report::whereIn('status', ['pending', 'assistant_revision', 'awaiting_admin'])
        ->where('created_at', '<', now()->subDay())
        ->each(function (Report $report) use ($recipients) {
            DB::transaction(function () use ($report, $recipients) {
                $current = Report::lockForUpdate()->find($report->id);
                if (!$current || $current->status !== $report->status) {
                    return;
                }

                $role = $current->status === 'awaiting_admin' ? 'admin' : 'assistant';
                foreach ($recipients->get($role, collect()) as $recipient) {
                    $preferences = $recipient->preferences ?? [];
                    if (!($preferences['alerts']['overdue'] ?? true) ||
                        (!($preferences['in_app'] ?? true) && !($preferences['email'] ?? false))) {
                        continue;
                    }

                    $key = "notifications.overdue-report.{$current->id}.{$current->status}.{$recipient->id}";
                    if (DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => now()->toIso8601String()])) {
                        StaffAlerts::account($recipient, 'Overdue report: '.$current->title, '/workspace/report/'.$current->id, 'overdue');
                    }
                }
            });
        });
})->purpose('Send overdue report review reminders');

Schedule::command('notifications:overdue-reports')->hourly();
