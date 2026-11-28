@php
    $archived = request()->boolean('archive');
    $isLostFound = $page === 'lost-found';
    $statuses = $isLostFound ? App\Models\ServiceRequest::LOST_FOUND_STATUSES : App\Models\ServiceRequest::COMPLAINT_STATUSES;
    $reportTypes = App\Models\ServiceRequest::LOST_FOUND_TYPES;
    $listUrl = url('/workspace/'.$page);
    $record = $isLostFound ? 'report' : 'complaint';
    $listTitle = $isLostFound
        ? ($archived ? 'Archived Lost & Found Reports' : 'Lost & Found Record')
        : ($archived ? 'Archived Complaints' : 'Complainant');
@endphp

<div @class(['cvu-record-list', 'cvu-lost-found' => $isLostFound, 'cvu-complaints' => !$isLostFound])>
    @include('workspace.cvu-stat-cards', ['label' => $isLostFound ? 'Lost and found activity' : 'Complaint activity'])

    <div class="cvu-archive-row">
        <a class="cvu-archive-link" href="{{ $listUrl.($archived ? '' : '?archive=1') }}">
            <img src="/staff-assets/21-1230.svg" alt="">
            <span>
                <strong>{{ $archived ? 'Back to Active' : 'Find Archived' }}</strong>
                @unless($archived)<small>{{ $archivedRequestCount }} archived</small>@endunless
            </span>
            <span class="cvu-archive-arrow" aria-hidden="true">›</span>
        </a>
    </div>

    <section class="cvu-panel cvu-record-panel" aria-labelledby="cvu-record-title">
        <div class="cvu-record-toolbar">
            <h2 id="cvu-record-title"><img src="/staff-assets/21-1112.svg" alt="">{{ $listTitle }}</h2>
            <form method="get" action="{{ $listUrl }}" class="cvu-record-filters" role="search">
                @if($archived)<input type="hidden" name="archive" value="1">@endif
                <div class="cvu-record-search">
                    <button type="submit" aria-label="Search {{ $record }}s"><img src="/staff-assets/21-1234.svg" alt=""></button>
                    <input type="search" name="search" aria-label="{{ $isLostFound ? 'Search by reference number, reporter, item name, or report type' : 'Search by reference number, name, or complaint type' }}" placeholder="Search ref., name, or type" value="{{ request('search') }}">
                </div>
                <select name="status" aria-label="{{ ucfirst($record) }} status" data-submit-on-change>
                    <option value="">All Status</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="cvu-record-table" role="region" aria-labelledby="cvu-record-title" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Reference No.</th>
                        <th scope="col">{{ $isLostFound ? 'Reporter / Finder' : 'Violator' }}</th>
                        <th scope="col">Type</th>
                        <th scope="col">Date Submitted</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $item)
                        @php
                            $action = $item->reviewActions(auth()->user()) ? 'Review' : 'View';
                            $status = $isLostFound ? $item->lostFoundStatus() : $item->status;
                        @endphp
                        <tr>
                            <td class="cvu-record-muted">{{ $item->ticket }}</td>
                            <td>{{ $isLostFound ? $item->user->name : ($item->details['respondent'] ?? '—') }}</td>
                            <td>{{ $isLostFound ? $reportTypes[$item->kind] : ($item->complaintType() ?? '—') }}</td>
                            <td class="cvu-record-muted"><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></td>
                            <td><span class="cvu-record-status is-{{ $status }}">{{ $statuses[$status] ?? str($status)->headline() }}</span></td>
                            <td><a class="cvu-record-action" href="{{ request()->fullUrlWithQuery(['review' => $item->id, 'report' => null]) }}" aria-label="{{ $action }} {{ $item->ticket }}">{{ $action }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="cvu-record-empty">No {{ $archived ? 'archived ' : '' }}{{ $record }}s found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="cvu-record-footer">
            <span>{{ $requests->total() }} {{ Str::plural($record, $requests->total()) }}</span>
            {{ $requests->appends(['review' => null, 'report' => null])->links('workspace.sfu-pagination', ['paginationLabel' => ucfirst($record).' pages']) }}
            <a href="/staff/export">Export CSV</a>
        </div>
    </section>
</div>
