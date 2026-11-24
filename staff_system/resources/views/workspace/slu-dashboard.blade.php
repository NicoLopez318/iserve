@php
    $organizationsById = $organizations->keyBy('id');
@endphp

@include('workspace.unit-stat-cards')

<div @class(['slu-dashboard-grid', 'slu-dashboard-expanded' => $fullSluQueue])>
    <section class="sfu-panel slu-action-queue" aria-labelledby="slu-action-title">
        <div class="slu-queue-heading">
            <h2 id="slu-action-title"><img src="/staff-assets/21-5492.svg" alt="">Action Queue</h2>
            @if($fullSluQueue)
                <a href="{{ request()->fullUrlWithoutQuery(['queue','transactions','review','report']) }}">Back to dashboard</a>
            @else
                <a href="{{ request()->fullUrlWithQuery(['queue'=>'all','transactions'=>null,'review'=>null,'report'=>null]) }}">View all <img src="/staff-assets/21-5493.svg" alt=""></a>
            @endif
        </div>

        <div class="sfu-table-wrap slu-queue-table">
            <table>
                <thead><tr>
                    <th scope="col">Item</th><th scope="col">Organization</th><th scope="col">Module</th>
                    <th scope="col">Submitted</th><th scope="col">Status</th><th scope="col">Action</th>
                </tr></thead>
                <tbody>
                    @forelse($requests as $item)
                        @php
                            $details = $item->details ?? [];
                            $module = match($item->kind) {
                                'activity' => empty($details['completion_report']) ? 'Proposal' : 'Event Report',
                                'leadership' => 'Membership',
                                default => empty($details['adjustment']) ? 'Achievement' : 'Point Adjustment',
                            };
                            $organization = $organizationsById->get($details['organization_id'] ?? $item->user->organization_id);
                            $status = match($item->status) {
                                'pending' => match($module) {
                                    'Membership' => 'For Validation', 'Achievement' => 'Evidence Review',
                                    default => $module.' Review',
                                },
                                'in_review' => 'Under Review', 'needs_revision' => 'For Revision',
                                'unit_approved' => 'For Endorsement', 'awaiting_admin' => 'Under Admin Review',
                                default => str($item->status)->headline(),
                            };
                            $action = match($item->status) {
                                'pending', 'in_review' => $module === 'Membership' ? 'Validate' : 'Review',
                                'needs_revision' => 'Follow Up',
                                'unit_approved', 'awaiting_admin' => 'Track',
                                default => 'View',
                            };
                            $title = $details['title'] ?? ($module === 'Membership' ? $item->user->name : $item->ticket);
                        @endphp
                        <tr>
                            <td>{{ $title }}</td><td>{{ $organization?->name ?? '—' }}</td><td>{{ $module }}</td>
                            <td><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('M j, Y') }}</time></td>
                            <td><span class="slu-queue-status is-{{ $item->status }}">{{ $status }}</span></td>
                            <td><a class="sfu-button" href="{{ request()->fullUrlWithQuery(['review'=>$item->id,'report'=>null]) }}" aria-label="{{ $action }} {{ $title }}">{{ $action }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="sfu-empty">No submissions in the action queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($fullSluQueue && $requests->hasPages())
            <div class="sfu-queue-footer">{{ $requests->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel'=>'Queue pages']) }}</div>
        @endif
    </section>

    <aside class="slu-dashboard-side" aria-label="Leadership updates">
        <section class="sfu-panel slu-side-panel" aria-labelledby="slu-deadlines-title">
            <h2 id="slu-deadlines-title"><img src="/staff-assets/21-5516.svg" alt="">Upcoming Deadlines</h2>
            <ul class="slu-update-list">
                @forelse($sluDeadlines as $deadline)
                    <li>
                        <a class="slu-deadline" href="{{ $deadline['url'] }}">
                            <time class="slu-deadline-date" datetime="{{ $deadline['date']->toIso8601String() }}"><span>{{ strtoupper($deadline['date']->format('M')) }}</span>{{ $deadline['date']->format('j') }}</time>
                            <span class="slu-update-text"><strong>{{ $deadline['title'] }}</strong><small>{{ $deadline['label'] }}</small></span>
                            <img class="slu-deadline-arrow" src="/staff-assets/21-5534.svg" alt="">
                        </a>
                    </li>
                @empty
                    <li class="sfu-empty">No upcoming deadlines.</li>
                @endforelse
            </ul>
        </section>

        <section class="sfu-panel slu-side-panel" aria-labelledby="slu-activity-title">
            <h2 id="slu-activity-title"><img src="/staff-assets/21-5519.svg" alt="">Recent Activity</h2>
            <ul class="slu-update-list">
                @forelse($sluActivity as $entry)
                    <li class="slu-activity-row">
                        <span class="slu-update-text"><strong>{{ $entry['title'] }}</strong><small>{{ $entry['subject'] }}</small></span>
                        <time datetime="{{ $entry['date']->toIso8601String() }}" title="{{ $entry['date']->format('M j, Y g:i A') }}">{{ $entry['date']->diffForHumans() }}</time>
                    </li>
                @empty
                    <li class="sfu-empty">No recent activity.</li>
                @endforelse
            </ul>
        </section>
    </aside>
</div>
