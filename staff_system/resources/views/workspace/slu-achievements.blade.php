@php
    $organizationsById = $organizations->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->keyBy('id');
    $statuses = [
        'pending' => 'Pending Review', 'in_review' => 'Under Review', 'needs_revision' => 'Needs Revision',
        'unit_approved' => 'Endorsed to Assistant', 'awaiting_admin' => 'Under Admin Review',
        'approved' => 'Approved', 'rejected' => 'Declined', 'cancelled' => 'Cancelled',
    ];
    $recordedPoints = $points->keyBy('source');
@endphp

<section class="sfu-panel slu-achievement-queue" aria-labelledby="slu-achievement-queue-title">
    <div class="slu-queue-heading">
        <h2 id="slu-achievement-queue-title">
            <img src="/staff-assets/21-5492.svg" alt="">
            Achievements Submissions
        </h2>
    </div>
    <p class="slu-achievement-intro">Review student and organization achievements submitted for approval.</p>

    <form class="sfu-filters slu-proposal-filters slu-achievement-filters" method="get" action="/workspace/achievements" data-sfu-filters>
        @if(request()->boolean('archive'))<input type="hidden" name="archive" value="1">@endif
        <div class="sfu-search">
            <button type="submit" aria-label="Apply achievement filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
            <input type="search" name="search" placeholder="Search achievements, organizations..."
                   aria-label="Search by achievement, organization, applicant, or ticket" value="{{ request()->string('search') }}">
        </div>
        <select name="status" aria-label="Achievement status">
            <option value="">All Statuses</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="organization" aria-label="Organization">
            <option value="">All Organizations</option>
            @foreach($organizationsById as $organization)
                <option value="{{ $organization->id }}" @selected(request()->integer('organization') === $organization->id)>{{ $organization->name }}</option>
            @endforeach
        </select>
        <select name="category" aria-label="Achievement category">
            <option value="">All Categories</option>
            @foreach($achievementCategories as $category)
                <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </form>

    <div class="sfu-table-wrap slu-queue-table slu-achievement-table" role="region" aria-label="Achievement submissions" tabindex="0">
        <table>
            <thead><tr>
                <th scope="col">Achievement</th><th scope="col">Organization</th><th scope="col">Achieved Date</th>
                <th scope="col">Evidence</th><th scope="col">Suggested Points</th><th scope="col">Status</th><th scope="col">Action</th>
            </tr></thead>
            <tbody>
                @forelse($requests as $item)
                    @php
                        $details = $item->details ?? [];
                        $title = $details['title'] ?? $item->ticket;
                        $organization = $organizationsById->get($details['organization_id'] ?? $item->user->organization_id ?? 0);
                        $date = isset($details['achievement_date'])
                            ? rescue(fn () => \Illuminate\Support\Carbon::parse($details['achievement_date']), report: false) : null;
                        $files = collect(array_keys($item->files ?? []))->filter(fn ($key) => $item->attachmentPath($key) !== null)->count();
                        $active = in_array($item->status, ['pending', 'in_review', 'needs_revision', 'unit_approved', 'awaiting_admin'], true);
                        $suggestion = $recordedPoints->get('achievement:'.$item->id)?->amount
                            ?? $details['awarded_points'] ?? ($active ? ($rules['achievement'] ?? 100) : null);
                        $missingEvidence = !$files && in_array($item->status, ['pending', 'in_review', 'needs_revision'], true);
                        $status = $missingEvidence ? 'Missing Evidence' : ($statuses[$item->status] ?? str($item->status)->headline());
                        $canReview = (bool) $item->reviewActions(auth()->user());
                    @endphp
                    <tr>
                        <td>{{ $title }}</td>
                        <td class="slu-achievement-meta">{{ $organization?->name ?? '—' }}</td>
                        <td class="slu-achievement-meta">@if($date)<time datetime="{{ $date->toDateString() }}">{{ $date->format('M j, Y') }}</time>@else — @endif</td>
                        <td><span @class(['slu-achievement-evidence', 'is-missing' => !$files])>
                            <img src="/staff-assets/21-5492.svg" alt="">{{ $files ? $files.' '.Str::plural('File', $files) : 'No evidence' }}
                        </span></td>
                        <td class="slu-achievement-meta">{{ is_numeric($suggestion) ? number_format($suggestion) : '—' }}</td>
                        <td><span class="slu-queue-status is-{{ $missingEvidence ? 'missing' : $item->status }}">{{ $status }}</span></td>
                        <td><a @class(['sfu-button', 'sfu-outline' => !$canReview])
                               href="{{ request()->fullUrlWithQuery(['review' => $item->id, 'report' => null]) }}"
                               aria-label="{{ $canReview ? 'Review' : 'View' }} {{ $title }}">{{ $canReview ? 'Review' : 'View' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="sfu-empty">No achievement submissions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
        <div class="sfu-queue-footer">{{ $requests->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Achievement pages']) }}</div>
    @endif
</section>
