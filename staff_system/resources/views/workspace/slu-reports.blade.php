@php
    $statuses = [
        'draft' => 'Draft',
        'pending' => 'Under Assistant Review',
        'awaiting_admin' => 'Under Admin Review',
        'needs_revision' => 'Returned for Revision',
        'assistant_revision' => 'Returned to Assistant',
        'approved' => 'Acknowledged',
        'rejected' => 'Declined',
    ];
@endphp

<nav class="slu-report-shortcuts" aria-label="Report actions">
    <button type="button" class="sfu-button" data-dialog="prepare-report">Prepare Unit Report</button>
    @foreach(['draft' => 'Draft Reports', 'needs_revision' => 'Returned Reports'] as $status => $label)
        <a class="sfu-button" @if(request('status') === $status) aria-current="page" @endif
           href="{{ request()->fullUrlWithQuery(['status' => request('status') === $status ? null : $status, 'reports' => null, 'report' => null, 'review' => null]) }}">
            {{ $label }}
        </a>
    @endforeach
</nav>

<section class="sfu-panel slu-unit-reports" aria-labelledby="slu-unit-reports-title">
    <div class="slu-unit-reports-heading">
        <div class="slu-points-heading-copy">
            <img src="/staff-assets/21-5492.svg" alt="">
            <div>
                <h2 id="slu-unit-reports-title">Unit Reports</h2>
                <p>Consolidated reports prepared from organization records.</p>
            </div>
        </div>

        <form class="sfu-filters slu-unit-report-filters" method="get" action="/workspace/reports" data-sfu-filters>
            <div class="sfu-search">
                <button type="submit" aria-label="Apply report filters"><img src="/staff-assets/21-4727.svg" alt=""></button>
                <input type="search" name="search" aria-label="Search reports by title or ID" placeholder="Search reports..."
                       value="{{ request()->string('search') }}">
            </div>
            <label>Reporting Period
                <select name="period">
                    <option value="">All Periods</option>
                    @foreach($reportPeriods as $value => $label)
                        <option value="{{ $value }}" @selected(request('period') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    <div class="sfu-table-wrap slu-queue-table slu-unit-report-table" role="region" aria-label="Unit reports" tabindex="0">
        <table>
            <thead><tr>
                <th scope="col">Report</th><th scope="col">Period</th><th scope="col">Source Coverage</th>
                <th scope="col">Prepared</th><th scope="col">Status</th><th scope="col">Action</th>
            </tr></thead>
            <tbody>
                @forelse($reports as $report)
                    @php
                        $snapshot = $report->snapshot ?? [];
                        $sources = $snapshot['records'] ?? (array_is_list($snapshot) ? $snapshot : []);
                        $sourceCount = count($sources);
                        $summaries = count($snapshot['activity'] ?? []);
                        $month = App\Models\Report::reportingMonth($snapshot['date_from'] ?? null, $snapshot['date_to'] ?? null);
                        $prepared = $report->created_at;
                        $action = match($report->status) {
                            'draft' => 'Edit',
                            'needs_revision' => 'Revise',
                            'pending', 'awaiting_admin', 'assistant_revision' => 'Track',
                            default => 'View',
                        };
                    @endphp
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td class="slu-report-meta">
                            @if($month)
                                {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format('F Y') }}
                            @else
                                {{ $report->semester }}<br>{{ $report->academic_year }}
                            @endif
                        </td>
                        <td class="slu-report-meta">
                            {{ number_format($sourceCount) }} {{ Str::plural('source record', $sourceCount) }}
                            @if($summaries)<small>{{ number_format($summaries) }} {{ Str::plural('activity summary', $summaries) }}</small>@endif
                        </td>
                        <td><time datetime="{{ $prepared->toDateString() }}">{{ $prepared->format('M j, Y') }}</time></td>
                        <td><span class="sfu-report-status slu-unit-report-status is-{{ $report->status }}">{{ $statuses[$report->status] ?? str($report->status)->headline() }}</span></td>
                        <td><a @class(['sfu-button', 'sfu-outline' => $action === 'View'])
                               href="{{ request()->fullUrlWithQuery(['report' => $report->id, 'review' => null]) }}"
                               aria-label="{{ $action }} {{ $report->title }}">{{ $action }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sfu-empty">No reports found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($reports->hasPages())
        <div class="sfu-queue-footer">{{ $reports->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Unit report pages']) }}</div>
    @endif

    <details class="slu-report-preview">
        <summary>Unit Activity Totals</summary>
        <dl>
            @foreach(config('portal.office.slu_head', []) as $kind)
                <div><dt>{{ App\Models\ServiceRequest::kinds()[$kind] }}</dt><dd>{{ number_format($sluReportActivity[$kind] ?? 0) }}</dd></div>
            @endforeach
        </dl>
    </details>
</section>
