<?php

namespace App\Support;

use App\Models\{Event, User};
use Illuminate\Support\Facades\DB;

final class ActivityDocumentReminders
{
    public static function forStudent(User $student): array
    {
        if ($student->isStaff() || !$student->organization_id) {
            return [];
        }

        $reminders = [];
        $draft = DB::table('proposal_drafts')->where('user_id', $student->id)->first();
        if ($draft) {
            $details = json_decode($draft->details, true) ?? [];
            $files = json_decode($draft->files, true) ?? [];
            $missing = self::missingDocuments('pre_', $files);
            if ($missing && self::isOffCampus($details, $files)) {
                $reminders[] = self::entry('Pre-activity documents', $details['title'] ?? 'Activity proposal draft', '/apply/activity', 'Open draft', $missing);
            }
        }

        $requests = $student->requests()->where('kind', 'activity')->whereNotIn('status', ['cancelled', 'rejected'])->latest('id')->get();
        $proposals = $requests->reject(fn ($request) => !empty($request->details['completion_report']));
        $reports = $requests->filter(fn ($request) => !empty($request->details['completion_report']))->groupBy('details.event_id');
        $eventIds = DB::table('settings')->where('key', 'like', 'staff.event-source.%')->where('value->user_id', $student->id)
            ->pluck('value', 'key')->mapWithKeys(fn ($value, $key) => [
                (int) (json_decode($value, true)['proposal_id'] ?? 0) => (int) substr($key, strlen('staff.event-source.')),
            ]);
        $events = Event::whereKey($eventIds->values()->all())->where('ends_at', '<=', now())->get()->keyBy('id');

        foreach ($proposals as $proposal) {
            $details = $proposal->details ?? [];
            $files = $proposal->files ?? [];
            $title = $details['title'] ?? $proposal->ticket;
            $offCampus = self::isOffCampus($details, $files);
            if ($proposal->status !== 'approved') {
                $missing = self::missingDocuments('pre_', $files);
                if ($offCampus && $missing) {
                    $reminders[] = self::entry('Pre-activity documents', $title, '/requests/'.$proposal->id,
                        $proposal->status === 'needs_revision' ? 'Revise proposal' : 'View proposal status', $missing);
                }
                continue;
            }

            $event = $events->get($eventIds->get($proposal->id));
            if (!$event || $event->status === 'cancelled') {
                continue;
            }
            $report = $reports->get($event->id)?->first();
            if ($report?->status === 'needs_revision') {
                $reminders[] = self::entry('Post-activity report needs revision', $title, '/requests/'.$report->id, 'Revise report');
            } elseif (!$report && $event->status === 'published') {
                $reminders[] = self::entry('Post-activity report due', $title, route('activity.completion', $proposal), 'Open after-event report',
                    $offCampus ? self::missingDocuments('post_', $files) : []);
            }
        }

        return $reminders;
    }

    private static function isOffCampus(array $details, array $files): bool
    {
        if (array_key_exists('off_campus', $details)) {
            return (bool) $details['off_campus'];
        }

        return (bool) array_filter(array_keys(array_filter($files)), fn ($key) => str_starts_with($key, 'pre_') || str_starts_with($key, 'post_'));
    }

    private static function missingDocuments(string $prefix, array $files): array
    {
        return array_filter(array_diff_key(config('portal.documents.activity'), array_filter($files)),
            fn ($key) => str_starts_with($key, $prefix), ARRAY_FILTER_USE_KEY);
    }

    private static function entry(string $stage, string $title, string $url, string $action, array $documents = []): array
    {
        return compact('stage', 'title', 'url', 'action', 'documents');
    }
}
