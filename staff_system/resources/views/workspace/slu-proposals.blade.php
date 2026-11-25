@php
    $organizationsById = $organizations->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->keyBy('id');
    $eventsById = $events->keyBy('id');
    $proposalDocuments = App\Models\ServiceRequest::PROPOSAL_CHECKLIST['Pre-Activity'];
    $statuses = [
        'pending' => 'Awaiting Review',
        'in_review' => 'Under Review',
        'needs_revision' => 'Needs Revision',
        'unit_approved' => 'Endorsed to Assistant',
        'awaiting_admin' => 'Under Admin Review',
        'approved' => 'Approved',
        'rejected' => 'Declined',
        'cancelled' => 'Cancelled',
    ];
@endphp

<section class="sfu-panel slu-proposal-queue" aria-labelledby="slu-proposal-queue-title">
    <div class="slu-queue-heading">
        <h2 id="slu-proposal-queue-title"><img src="/staff-assets/21-5492.svg" alt="">Proposal Queue</h2>
        @if($fullProposalQueue)
            <a href="{{ request()->fullUrlWithoutQuery(['queue', 'transactions', 'review', 'report']) }}">Back to queue</a>
        @else
            <a href="{{ request()->fullUrlWithQuery(['queue' => 'all', 'transactions' => null, 'review' => null, 'report' => null]) }}">
                View all <img src="/staff-assets/21-5493.svg" alt="">
            </a>
        @endif
    </div>

    <form class="sfu-filters slu-proposal-filters" method="get" action="/workspace/proposals" data-sfu-filters>
        @if($fullProposalQueue)<input type="hidden" name="queue" value="all">@endif
        <div class="sfu-search">
            <button type="submit" aria-label="Apply proposal filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
            <input type="search" name="search" placeholder="Search proposal..." value="{{ request()->string('search') }}"
                   aria-label="Search by proposal, organization, applicant, or ticket">
        </div>
        <select name="organization" aria-label="Organization">
            <option value="">All Organizations</option>
            @foreach($organizationsById as $organization)
                <option value="{{ $organization->id }}" @selected(request()->integer('organization') === $organization->id)>{{ $organization->name }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="Proposal status">
            <option value="">All Status</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="sfu-table-wrap slu-queue-table slu-proposal-table" role="region" aria-label="Proposal queue" tabindex="0">
        <table>
            <thead>
                <tr>
                    <th scope="col">Proposal</th><th scope="col">Organization</th><th scope="col">Event Date</th>
                    <th scope="col">Submitted</th><th scope="col">Requirements</th><th scope="col">Status</th><th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $item)
                    @php
                        $details = $item->details ?? [];
                        $title = $details['title'] ?? $item->ticket;
                        $organization = $organizationsById->get($details['organization_id'] ?? $item->user->organization_id ?? 0);
                        $completionReport = !empty($details['completion_report']);
                        $eventDate = $completionReport
                            ? $eventsById->get($details['event_id'] ?? 0)?->starts_at
                            : (empty($details['starts_at']) ? null : rescue(fn () => \Illuminate\Support\Carbon::parse($details['starts_at']), report: false));
                        $requirements = $completionReport ? ['completion_report'] : $proposalDocuments;
                        $attached = collect($requirements)->filter(fn ($key) => $item->attachmentPath($key) !== null)->count();
                        $complete = $attached === count($requirements);
                        $status = match (true) {
                            !$completionReport && in_array($item->status, ['pending', 'in_review'], true)
                                && $eventDate?->isPast() => ['overdue', 'Overdue Review'],
                            $item->status === 'in_review' && $complete => ['ready', 'Ready to Endorse'],
                            default => [$item->status, $statuses[$item->status] ?? str($item->status)->headline()],
                        };
                        $action = $item->reviewActions(auth()->user()) ? 'Review' : 'View';
                    @endphp
                    <tr>
                        <td>{{ $title }}</td>
                        <td>{{ $organization?->name ?? '—' }}</td>
                        <td class="slu-proposal-date">@if($eventDate)<time datetime="{{ $eventDate->toDateString() }}">{{ $eventDate->format('M j, Y') }}</time>@else — @endif</td>
                        <td class="slu-proposal-date"><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></td>
                        <td><span @class(['slu-proposal-requirements', 'is-complete' => $complete])>{{ $attached }} of {{ count($requirements) }} attached</span></td>
                        <td><span class="slu-queue-status is-{{ $status[0] }}">{{ $status[1] }}</span></td>
                        <td><a class="sfu-button" href="{{ request()->fullUrlWithQuery(['review' => $item->id, 'report' => null]) }}"
                               aria-label="{{ $action }} {{ $title }}">{{ $action }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="sfu-empty">No proposals found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($fullProposalQueue && $requests->hasPages())
        <div class="sfu-queue-footer">{{ $requests->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Proposal pages']) }}</div>
    @endif
</section>
