@php
    ['rankings' => $rankings, 'ledger' => $ledger, 'departments' => $departments, 'terms' => $terms, 'hasUnassigned' => $hasUnassigned] = $leadershipPoints;
    $fullLedger = request('ledger') === 'all';
    $organizationsById = $organizations->keyBy('id');
    $signedPoints = fn ($points) => ($points > 0 ? '+' : '').number_format($points);
@endphp

<section class="sfu-panel slu-rankings" aria-labelledby="slu-rankings-title">
    <div class="slu-points-heading">
        <div class="slu-points-heading-copy">
            <img src="/staff-assets/21-5492.svg" alt="">
            <div>
                <h2 id="slu-rankings-title">Current Rankings</h2>
                <p>Organization ranking based on recorded point transactions.</p>
            </div>
        </div>
        <form class="sfu-filters slu-ranking-filters" method="get" action="/workspace/points" data-sfu-filters>
            @if($fullLedger)<input type="hidden" name="ledger" value="all">@endif
            <label>Academic Term
                <select name="term">
                    <option value="">All terms</option>
                    @foreach($terms as $term)<option value="{{ $term }}" @selected(request('term') === $term)>{{ $term }}</option>@endforeach
                    @if($hasUnassigned)<option value="unassigned" @selected(request('term') === 'unassigned')>Unassigned term</option>@endif
                </select>
            </label>
            <label>Department
                <select name="department">
                    <option value="">All</option>
                    @foreach($departments as $department)<option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>@endforeach
                </select>
            </label>
            <noscript><button type="submit">Apply filters</button></noscript>
        </form>
    </div>

    <div class="sfu-table-wrap slu-queue-table slu-points-table slu-rankings-table" role="region" aria-label="Organization rankings" tabindex="0">
        <table>
            <thead><tr>
                <th scope="col">Rank</th><th scope="col">Organization</th><th scope="col">Level</th>
                <th scope="col">Participation</th><th scope="col">Achievement</th><th scope="col">Adjustment</th>
                <th scope="col">Total Points</th><th scope="col">Change</th>
            </tr></thead>
            <tbody>
                @forelse($rankings as $row)
                    @php
                        $organization = $row['organization'];
                        $initial = str($row['acronym'] ?: $organization->name)->substr(0, 1)->upper();
                        $level = App\Support\LeadershipPoints::levelIndex($row['total']);
                        $levelName = App\Support\LeadershipPoints::LEVELS[$level]['name'];
                    @endphp
                    <tr>
                        <td><span class="slu-rank-number">{{ $row['rank'] }}</span></td>
                        <td><span class="slu-org-name"><span class="slu-org-initials is-color-{{ $organization->id % 4 }}" aria-hidden="true">{{ $initial }}</span>{{ $organization->name }}</span></td>
                        <td><span title="{{ $levelName }}">Level {{ $level + 1 }}</span></td>
                        <td>{{ number_format($row['participation']) }}</td><td>{{ number_format($row['achievement']) }}</td>
                        <td><span @class(['slu-points-positive' => $row['adjustment'] > 0, 'slu-points-negative' => $row['adjustment'] < 0])>{{ $signedPoints($row['adjustment']) }}</span></td>
                        <td><strong>{{ number_format($row['total']) }}</strong></td>
                        <td>
                            @if($row['change'])
                                <span @class(['slu-points-positive' => $row['change'] > 0, 'slu-points-negative' => $row['change'] < 0])
                                      aria-label="{{ $row['change'] > 0 ? 'Up' : 'Down' }} {{ abs($row['change']) }} {{ Str::plural('place', abs($row['change'])) }}">{{ $row['change'] > 0 ? '↑' : '↓' }} {{ abs($row['change']) }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="sfu-empty">No organizations match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="slu-rankings-footer">
        <small>Change compares rank before and after the latest recorded transaction day in this view.</small>
        <button type="button" class="sfu-button sfu-outline" data-dialog="scoring-rules">Scoring Rules</button>
    </div>
</section>

<div class="slu-points-detail">
    <section class="sfu-panel slu-points-card" id="point-ledger" aria-labelledby="slu-ledger-title">
        <div class="slu-points-heading">
            <div class="slu-points-heading-copy">
                <img src="/staff-assets/21-5492.svg" alt="">
                <div><h2 id="slu-ledger-title">Point Ledger</h2><p>Point transactions affecting organization rankings.</p></div>
            </div>
            @if($fullLedger)
                <a href="{{ request()->fullUrlWithoutQuery(['ledger', 'ledger_page', 'review', 'report']) }}#point-ledger">Summary</a>
            @else
                <a href="{{ request()->fullUrlWithQuery(['ledger' => 'all', 'ledger_page' => null, 'review' => null, 'report' => null]) }}#point-ledger">View all</a>
            @endif
        </div>
        <div class="sfu-table-wrap slu-queue-table slu-points-table slu-ledger-table" role="region" aria-label="Point ledger" tabindex="0">
            <table>
                <thead><tr>
                    <th scope="col">Event / Activity</th><th scope="col">Organization</th><th scope="col">Type</th>
                    <th scope="col">Points</th><th scope="col">Date</th><th scope="col">Source</th><th scope="col">Action</th>
                </tr></thead>
                <tbody>
                    @forelse($ledger as $entry)
                        @php
                            $point = $entry['point'];
                        @endphp
                        <tr>
                            <td>{{ $point->reason }}</td><td>{{ $entry['organization']?->name ?? '—' }}</td><td>{{ $entry['type'] }}</td>
                            <td><span @class(['slu-points-positive' => $entry['amount'] > 0, 'slu-points-negative' => $entry['amount'] < 0])>{{ $signedPoints($entry['amount']) }}</span></td>
                            <td><time datetime="{{ $point->created_at->toDateString() }}">{{ $point->created_at->format('M j, Y') }}</time></td>
                            <td><span @class(['slu-points-positive' => $entry['origin'] === 'Approved'])>{{ $entry['origin'] }}</span></td>
                            <td>
                                @if($entry['url'])
                                    <a class="sfu-button sfu-outline" href="{{ $entry['url'] }}" aria-label="View source for {{ $point->reason }}">View Source</a>
                                @else
                                    <span class="slu-point-reference">{{ $point->source }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="sfu-empty">No point transactions match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($fullLedger && $ledger->hasPages())
            <div class="sfu-queue-footer">{{ $ledger->fragment('point-ledger')->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Ledger pages']) }}</div>
        @endif
    </section>

    <section class="sfu-panel slu-points-card" id="pending-adjustments" aria-labelledby="slu-adjustments-title">
        <div class="slu-points-heading">
            <div class="slu-points-heading-copy slu-adjustments-heading">
                <img src="/staff-assets/21-5561.svg" alt="">
                <div><h2 id="slu-adjustments-title">Pending Adjustments</h2><p>Adjustments awaiting Assistant or Admin review.</p></div>
            </div>
        </div>
        <div class="sfu-table-wrap slu-queue-table slu-points-table slu-adjustments-table" role="region" aria-label="Pending adjustments" tabindex="0">
            <table>
                <thead><tr><th scope="col">Organization</th><th scope="col">Reason</th><th scope="col">Points</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @forelse($requests as $adjustment)
                        @php
                            $organization = $organizationsById->get($adjustment->user?->organization_id ?? 0);
                            $amount = (int) ($adjustment->details['awarded_points'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $organization?->name ?? '—' }}</td>
                            <td>{{ $adjustment->details['description'] ?? $adjustment->ticket }}</td>
                            <td><span @class(['slu-points-positive' => $amount > 0, 'slu-points-negative' => $amount < 0])>{{ $signedPoints($amount) }}</span></td>
                            <td><a class="sfu-button" href="{{ request()->fullUrlWithQuery(['review' => $adjustment->id, 'report' => null]) }}"
                                   aria-label="View adjustment {{ $adjustment->ticket }}">View Adjustment</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="sfu-empty">No pending adjustments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="sfu-queue-footer">{{ $requests->fragment('pending-adjustments')->onEachSide(1)->links('workspace.sfu-pagination', ['paginationLabel' => 'Adjustment pages']) }}</div>
        @endif
        <div class="slu-adjustments-footer"><button type="button" class="sfu-button sfu-outline" data-dialog="point-adjustment">New Adjustment</button></div>
    </section>
</div>
