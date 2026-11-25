@php
    $organizationsById = $organizations->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->keyBy('id');
    $statuses = App\Models\ServiceRequest::MEMBERSHIP_STATUSES;
    $requiredDocuments = array_keys(config('portal.documents.leadership'));
@endphp

<form class="sfu-filters slu-org-filters slu-membership-filters" method="get" action="/workspace/organizations" data-sfu-filters>
    <input type="hidden" name="tab" value="membership">
    <div class="sfu-search">
        <button type="submit" aria-label="Apply membership filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
        <input type="search" name="search" aria-label="Search by organization, student, or application number"
               placeholder="Search organization..." value="{{ request()->string('search') }}">
    </div>
    <select name="organization" aria-label="Organization">
        <option value="">Organization</option>
        @foreach($organizationsById as $organization)
            <option value="{{ $organization->id }}" @selected(request()->integer('organization') === $organization->id)>{{ $organization->name }}</option>
        @endforeach
    </select>
    <select name="status" aria-label="Application status">
        <option value="">All Status</option>
        @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</form>

<div class="sfu-table-wrap slu-queue-table slu-membership-table" role="region" aria-label="Membership applications" tabindex="0">
    <table>
        <thead>
            <tr>
                <th scope="col">Application No.</th>
                <th scope="col">Student</th>
                <th scope="col">Organization</th>
                <th scope="col">Position</th>
                <th scope="col">Requirements</th>
                <th scope="col">Status</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $application)
                @php
                    $organization = $organizationsById->get($application->details['organization_id'] ?? 0);
                    $complete = collect($requiredDocuments)->every(fn ($key) => $application->attachmentPath($key) !== null);
                    $canReview = (bool) $application->reviewActions(auth()->user());
                @endphp
                <tr>
                    <td>{{ $application->ticket }}</td>
                    <td>{{ $application->user->name }}</td>
                    <td>{{ $organization?->name ?? '—' }}</td>
                    <td>{{ $application->details['position'] ?? '—' }}</td>
                    <td><span @class(['slu-org-status', 'is-active' => $complete, 'is-needs_attention' => !$complete])>{{ $complete ? 'Complete' : 'Missing' }}</span></td>
                    <td><span class="slu-membership-status is-{{ $application->status }}">{{ $statuses[$application->status] ?? str($application->status)->headline() }}</span></td>
                    <td><a class="sfu-button" href="{{ request()->fullUrlWithQuery(['tab' => 'membership', 'review' => $application->id, 'report' => null]) }}"
                           aria-label="{{ $canReview ? 'Review' : 'View' }} application {{ $application->ticket }}">{{ $canReview ? 'Review' : 'View' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="sfu-empty">No membership applications found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($requests->hasPages())
    <div class="slu-membership-pagination">{{ $requests->links('workspace.sfu-pagination') }}</div>
@endif
