@php
    $reportStatuses = [
        'draft' => 'Draft',
        'needs_revision' => 'Returned for Revisions',
        'pending' => 'Under Assistant Review',
        'awaiting_admin' => 'Forwarded to Director',
        'assistant_revision' => 'Returned to Assistant',
        'approved' => 'Acknowledged',
        'rejected' => 'Declined',
    ];
@endphp

@include('workspace.unit-stat-cards')

<section class="sfu-panel sfu-queue sfu-full-queue sfu-report-queue" aria-labelledby="sfu-reports-title">
    <h2 id="sfu-reports-title">Reports</h2>

    <form class="sfu-filters" method="get" data-sfu-filters>
        <div class="sfu-search">
            <button type="submit" aria-label="Apply report filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
            <input name="search" aria-label="Search reports" placeholder="Search reports ..." value="{{ request('search') }}">
        </div>
        <select name="semester" aria-label="Semester">
            <option value="">Semester</option>
            @foreach(['1st Semester', '2nd Semester', 'Summer'] as $semester)
                <option value="{{ $semester }}" @selected(request('semester') === $semester)>{{ $semester }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="Report status">
            <option value="">Report Status</option>
            @foreach($reportStatuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="sfu-table-wrap">
        <table>
            <thead><tr>
                <th scope="col">Report</th><th scope="col">Period</th><th scope="col">Application</th>
                <th scope="col">Prepared</th><th scope="col">Status</th><th scope="col">Action</th>
            </tr></thead>
            <tbody>
                @forelse($reports as $report)
                    @php
                        $snapshot = $report->snapshot ?? [];
                        $records = $snapshot['records'] ?? (array_is_list($snapshot) ? $snapshot : []);
                        $action = match($report->status) {
                            'draft', 'needs_revision' => 'Edit',
                            'pending', 'awaiting_admin', 'assistant_revision' => 'Track',
                            default => 'View',
                        };
                    @endphp
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->semester }}<br>AY {{ $report->academic_year }}</td>
                        <td>{{ count($records) }}</td>
                        <td><time datetime="{{ $report->created_at->toDateString() }}">{{ $report->created_at->format('M j') }}</time></td>
                        <td><span class="sfu-report-status is-{{ $report->status }}">{{ $reportStatuses[$report->status] ?? str($report->status)->headline() }}</span></td>
                        <td><a class="sfu-button" href="{{ request()->fullUrlWithQuery(['report' => $report->id, 'review' => null]) }}" aria-label="{{ $action }} {{ $report->title }}">{{ $action }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sfu-empty">No reports found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($reports->count())
        <div class="sfu-queue-footer">
            <div class="sfu-queue-range">Showing {{ $reports->firstItem() }} to {{ $reports->lastItem() }} of {{ $reports->total() }} {{ Str::plural('report', $reports->total()) }}</div>
            {{ $reports->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Report pages', 'showSinglePage' => true]) }}
        </div>
    @endif
</section>
