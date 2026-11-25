<?php

namespace App\Support;

use App\Models\{Attendance, Event, Organization, Point, ServiceRequest};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadershipPoints
{
    public const LEVELS = [
        ['name' => 'Leader', 'minimum' => 0],
        ['name' => 'Amasser', 'minimum' => 500],
        ['name' => 'Hoarder', 'minimum' => 1000],
        ['name' => 'Stockpiler', 'minimum' => 2000],
    ];

    public static function levelIndex(int $total): int
    {
        foreach (array_reverse(self::LEVELS, true) as $index => $level) {
            if ($total >= $level['minimum']) return $index;
        }

        return 0;
    }

    public static function dashboard(Collection $organizations, Collection $points, string $term, string $department): array
    {
        $organizations = $organizations->keyBy('id');
        $metadata = DB::table('settings')->where('key', 'like', 'staff.organization.%')
            ->pluck('value', 'key')->map(fn ($value) => json_decode($value, true) ?? []);
        $departments = $organizations->map(fn ($organization) => $metadata->get('staff.organization.'.$organization->id)['college'] ?? '')
            ->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE);

        $ledger = self::ledger($points, $organizations);
        $terms = $ledger->pluck('term')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE);
        $hasUnassigned = $ledger->contains(fn ($entry) => $entry['term'] === '');
        $rankedOrganizations = $organizations->when($department !== '', fn ($list) => $list->filter(
            fn ($organization) => ($metadata->get('staff.organization.'.$organization->id)['college'] ?? '') === $department
        ));
        $ledger = $ledger
            ->when($department !== '', fn ($entries) => $entries->filter(fn ($entry) => $rankedOrganizations->has($entry['organization']?->id ?? 0)))
            ->when($term !== '', fn ($entries) => $entries->where('term', $term === 'unassigned' ? '' : $term));

        $rankableLedger = $ledger->filter(fn ($entry) => $rankedOrganizations->has($entry['organization']?->id ?? 0));
        $lastDay = $rankableLedger->max(fn ($entry) => $entry['point']->created_at->toDateString());
        $earlier = $rankableLedger->filter(fn ($entry) => $entry['point']->created_at->toDateString() < $lastDay);
        $previous = $earlier->isEmpty() ? collect() : self::rank($rankedOrganizations, $earlier)->pluck('rank', 'organization.id');
        $rankings = self::rank($rankedOrganizations, $rankableLedger)->map(function ($row) use ($metadata, $previous) {
            $id = $row['organization']->id;
            return $row + [
                'acronym' => $metadata->get('staff.organization.'.$id)['acronym'] ?? '',
                'change' => $previous->has($id) ? $previous[$id] - $row['rank'] : null,
            ];
        });

        $calculationValid = $rankings->sum('total') === $rankableLedger->sum('amount')
            && $rankings->every(fn ($row) => $row['total'] === $row['participation'] + $row['achievement'] + $row['adjustment']);

        return compact('rankings', 'ledger', 'departments', 'terms', 'hasUnassigned', 'calculationValid');
    }

    private static function ledger(Collection $points, Collection $organizations): Collection
    {
        $references = $points->map(fn (Point $point) => preg_match('/^([a-z-]+):([1-9][0-9]*)$/D', $point->source, $parts)
            ? [$parts[1], (int) $parts[2]] : ['', 0])->groupBy(fn ($parts) => $parts[0]);
        $ids = fn (string $type) => $references->get($type, collect())->pluck(1)->unique()->values();
        $requests = ServiceRequest::where('kind', 'achievement')->whereKey($ids('achievement')->all())->get()->keyBy('id');
        $attendance = Attendance::with('event')->whereKey($ids('attendance')->all())->get()->keyBy('id');
        $events = Event::whereKey($ids('event-completion')->merge($ids('completion-report'))->unique()->all())->get()->keyBy('id');
        $eventIds = $events->keys()->merge($attendance->pluck('event_id'))->filter()->unique();
        $eventSources = DB::table('settings')->whereIn('key', $eventIds->map(fn ($id) => 'staff.event-source.'.$id)->all())
            ->pluck('value', 'key')->map(fn ($value) => json_decode($value, true)['proposal_id'] ?? null);
        $proposals = ServiceRequest::where('kind', 'activity')->whereKey($eventSources->filter()->unique()->values()->all())->get()->keyBy('id');

        return $points->map(function (Point $point) use ($organizations, $requests, $attendance, $events, $eventSources, $proposals) {
            [$source, $reference] = array_pad(explode(':', $point->source, 2), 2, null);
            $request = $source === 'achievement' ? $requests->get($reference ?? 0) : null;
            $event = match ($source) {
                'attendance' => $attendance->get($reference ?? 0)?->event,
                'event-completion', 'completion-report' => $events->get($reference ?? 0),
                default => null,
            };
            $amount = (int) $point->amount;
            [$bucket, $type, $origin] = match (true) {
                !empty($request?->details['adjustment']) => ['adjustment', $amount < 0 ? 'Penalty' : 'Adjustment', 'Approved'],
                $source === 'achievement' => ['achievement', 'Achievement', $request ? 'Approved' : 'Recorded'],
                $source === 'attendance' => ['participation', 'Participation', 'Attendance'],
                $source === 'event-completion' => ['participation', 'Event Completion', 'System'],
                $source === 'completion-report' && $amount >= 0 => ['participation', 'Completion Report', 'System'],
                $source === 'completion-report' => ['adjustment', 'Penalty', 'System'],
                default => ['adjustment', 'Other', 'Recorded'],
            };
            $proposal = $event ? $proposals->get($eventSources->get('staff.event-source.'.$event->id) ?? 0) : null;
            $details = $request?->details ?? array_replace($proposal?->details ?? [], $event?->metadata ?? []);
            $term = filled($details['academic_year'] ?? null)
                ? implode(' ', array_filter([$details['semester'] ?? null, $details['academic_year']])) : '';
            $organization = $organizations->get($point->user?->organization_id ?? 0);
            $url = match (true) {
                $request !== null => url('/workspace/request/'.$request->id),
                $event?->type === 'leadership' => url('/workspace/events').'#event-'.$event->id.($source === 'attendance' ? '-attendance' : '-entry'),
                default => null,
            };

            return compact('point', 'organization', 'bucket', 'type', 'origin', 'term', 'url', 'amount');
        });
    }

    private static function rank(Collection $organizations, Collection $ledger): Collection
    {
        $entries = $ledger->groupBy(fn ($row) => $row['organization']->id);
        return $organizations->map(function (Organization $organization) use ($entries) {
            $totals = $entries->get($organization->id, collect())->groupBy('bucket')->map->sum('amount');
            return ['organization' => $organization, 'total' => $totals->sum()] + $totals->all()
                + ['participation' => 0, 'achievement' => 0, 'adjustment' => 0];
        })->sort(fn ($left, $right) => ($right['total'] <=> $left['total'])
            ?: strnatcasecmp($left['organization']->name, $right['organization']->name)
            ?: ($left['organization']->id <=> $right['organization']->id))
            ->values()->map(fn ($row, $index) => $row + ['rank' => $index + 1]);
    }
}
