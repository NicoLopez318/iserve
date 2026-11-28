@php
    $archived = request()->boolean('archive');
    $isLostFound = $page === 'lost-found';
    $isReadmission = $page === 'readmission';
    $isIdReplacement = $page === 'id-replacement';
    $statuses = match($page) {
        'lost-found' => App\Models\ServiceRequest::LOST_FOUND_STATUSES,
        'readmission' => App\Models\ServiceRequest::READMISSION_STATUSES,
        'id-replacement' => App\Models\ServiceRequest::ID_REPLACEMENT_STATUSES,
        default => App\Models\ServiceRequest::COMPLAINT_STATUSES,
    };
    $reportTypes = App\Models\ServiceRequest::LOST_FOUND_TYPES;
    $listUrl = url('/workspace/'.$page);
    [$record, $listTitle, $summaryLabel, $searchLabel] = match($page) {
        'lost-found' => ['report', $archived ? 'Archived Lost & Found Reports' : 'Lost & Found Record', 'Lost and found activity', 'Search by reference number, reporter, item name, or report type'],
        'readmission' => ['request', $archived ? 'Archived Re-Admission Requests' : 'Re-Admission Request', 'Re-admission activity', 'Search by reference number, student, or subject'],
        'id-replacement' => ['request', $archived ? 'Archived ID Replacement Requests' : 'ID Replacement Record', 'ID replacement activity', 'Search by reference number, student name, student ID, or type'],
        default => ['complaint', $archived ? 'Archived Complaints' : 'Complainant', 'Complaint activity', 'Search by reference number, name, or complaint type'],
    };
@endphp

<div @class(['cvu-record-list', 'cvu-lost-found' => $isLostFound, 'cvu-readmission' => $isReadmission, 'cvu-id-replacement' => $isIdReplacement, 'cvu-complaints' => $page === 'complaints'])>
    @include('workspace.cvu-stat-cards', ['label' => $summaryLabel])

    @unless($isIdReplacement)
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
    @endunless

    <section class="cvu-panel cvu-record-panel" aria-labelledby="cvu-record-title">
        <div class="cvu-record-toolbar">
            <h2 id="cvu-record-title"><img src="/staff-assets/21-1112.svg" alt="">{{ $listTitle }}</h2>
            <form method="get" action="{{ $listUrl }}" class="cvu-record-filters" role="search">
                @if($archived)<input type="hidden" name="archive" value="1">@endif
                <div class="cvu-record-search">
                    <button type="submit" aria-label="Search {{ $record }}s"><img src="/staff-assets/21-1234.svg" alt=""></button>
                    <input type="search" name="search" aria-label="{{ $searchLabel }}" placeholder="{{ $isReadmission ? 'Ref., student, or subject' : ($isIdReplacement ? 'Name, Student ID, or type' : 'Search ref., name, or type') }}" value="{{ request('search') }}">
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
                        <th scope="col">{{ $isReadmission || $isIdReplacement ? 'Student' : ($isLostFound ? 'Reporter / Finder' : 'Violator') }}</th>
                        <th scope="col">{{ $isReadmission ? 'Subject' : 'Type' }}</th>
                        @if($isIdReplacement)<th scope="col">Course</th>@endif
                        <th scope="col">Date Submitted</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $item)
                        @php
                            $action = $item->reviewActions(auth()->user()) ? 'Review' : 'View';
                            [$status, $person, $type] = match($page) {
                                'lost-found' => [$item->lostFoundStatus(), $item->user->name, $reportTypes[$item->kind]],
                                'readmission' => [$item->readmissionQueueStatus(), $item->details['applicant']['name'] ?? $item->user->name, $item->details['subject_code'] ?? $item->details['subject'] ?? '—'],
                                'id-replacement' => [$item->idReplacementQueueStatus(), $item->details['student_name'] ?? $item->user->name, $item->details['replacement_reason'] ?? '—'],
                                default => [$item->status, $item->details['respondent'] ?? '—', $item->complaintType() ?? '—'],
                            };
                        @endphp
                        <tr>
                            <td class="cvu-record-muted">{{ $item->ticket }}</td>
                            <td>{{ $person }}</td>
                            <td>{{ $type }}</td>
                            @if($isIdReplacement)<td class="cvu-record-muted">{{ $item->user->course ?? '—' }}</td>@endif
                            <td class="cvu-record-muted"><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></td>
                            <td><span class="cvu-record-status is-{{ $status }}">{{ $statuses[$status] ?? str($status)->headline() }}</span></td>
                            <td><a class="cvu-record-action" href="{{ request()->fullUrlWithQuery(['review' => $item->id, 'report' => null]) }}" aria-label="{{ $action }} {{ $item->ticket }}">{{ $action }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isIdReplacement ? 7 : 6 }}" class="cvu-record-empty">No {{ $archived ? 'archived ' : '' }}{{ $record }}s found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="cvu-record-footer">
            <span>{{ $requests->total() }} {{ Str::plural($record, $requests->total()) }}</span>
            {{ $requests->appends(['review' => null, 'report' => null])->links('workspace.sfu-pagination', ['paginationLabel' => ucfirst($record).' pages']) }}
            @if($isIdReplacement)<a href="{{ $listUrl.($archived ? '' : '?archive=1') }}">{{ $archived ? 'Back to Active' : 'Find Archived' }}</a>@endif
            <a href="{{ $isReadmission || $isIdReplacement ? '/staff/export?kind='.$page : '/staff/export' }}">Export CSV</a>
        </div>
    </section>
</div>
