@php
    $requiredDocuments = config('portal.documents.scholarship');
    $statuses = [
        'pending' => 'For Validation', 'in_review' => 'For Validation',
        'needs_revision' => 'For Compliance', 'unit_approved' => 'For Endorsement',
        'awaiting_admin' => 'Under Admin Review', 'approved' => 'Processed',
        'rejected' => 'Declined', 'cancelled' => 'Cancelled',
    ];
@endphp

<section @class(['sfu-panel sfu-queue', 'sfu-full-queue' => $fullQueue ?? false]) aria-labelledby="sfu-applications-title">
    <div class="sfu-queue-heading">
        <h2 id="sfu-applications-title">Applications</h2>
        @if($fullQueue ?? false)<button class="sfu-button" type="button" data-dialog="add-scholar">Add Scholar</button>@endif
    </div>
    <form class="sfu-filters" method="get" data-sfu-filters>
        <div class="sfu-search">
            <button type="submit" aria-label="Apply application filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
            <input name="search" aria-label="Search applications" placeholder="Search application ..." value="{{ request('search') }}">
        </div>
        <select name="status" aria-label="Application status">
            <option value="">Status</option>
            @foreach($statuses as $value => $label)
                @continue($value === 'in_review')
                <option value="{{ $value }}" @selected(request('status') === $value || ($value === 'pending' && request('status') === 'in_review'))>{{ $label }}</option>
            @endforeach
        </select>
        <select name="program" aria-label="Program">
            <option value="">Program</option>
            @foreach($sfuPrograms as $program)
                <option value="{{ $program }}" @selected(request('program') === $program)>{{ $program }}</option>
            @endforeach
        </select>
    </form>
    <div class="sfu-table-wrap">
        <table>
            <thead><tr>
                <th scope="col">Application No.</th><th scope="col">Applicant</th><th scope="col">Course</th>
                <th scope="col">Requirements</th><th scope="col">Status</th><th scope="col">Submitted</th><th scope="col">Action</th>
            </tr></thead>
            <tbody>
                @forelse($requests as $application)
                    @php
                        $submissionCount = count(array_intersect_key(array_filter($application->files ?? []), $requiredDocuments));
                        $rowAction = ($fullQueue ?? false) && !$application->reviewActions(auth()->user()) ? 'View' : 'Review';
                    @endphp
                    <tr>
                        <td>{{ $application->ticket }}</td>
                        <td>{{ $application->user->name }}</td>
                        <td>{{ $application->user->course ?: '—' }}</td>
                        <td><span class="{{ $submissionCount === count($requiredDocuments) ? 'sfu-complete' : 'sfu-incomplete' }}">{{ $submissionCount }} of {{ count($requiredDocuments) }} Complete</span></td>
                        <td><span class="sfu-status is-{{ $application->status }}">{{ $statuses[$application->status] ?? str($application->status)->headline() }}</span></td>
                        <td><time datetime="{{ $application->created_at->toDateString() }}">{{ $application->created_at->format('M j') }}</time></td>
                        <td><a class="sfu-button" href="{{ request()->fullUrlWithQuery(['review' => $application->id, 'report' => null]) }}" aria-label="{{ $rowAction }} {{ $application->ticket }}" @if($selectedRequest?->is($application)) aria-current="true" @endif>{{ $rowAction }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="sfu-empty">No applications found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($fullQueue ?? false)
        <div class="sfu-queue-footer">
            <div class="sfu-queue-range">
                @if($requests->count())<span>Showing {{ $requests->firstItem() }} to {{ $requests->lastItem() }} of {{ $requests->total() }} applications</span>@endif
                <a href="/staff/export">Export CSV</a>
            </div>
            {{ $requests->onEachSide(1)->links('workspace.sfu-pagination') }}
        </div>
    @elseif($requests->hasPages())
        <div class="pagination">{{ $requests->links() }}</div>
    @endif
</section>
