@php
    $services = [
        'complaint' => ['label' => 'Complaints & Concerns', 'icon' => '1095', 'page' => 'complaints'],
        'lost' => ['label' => 'Lost & Found', 'icon' => '1161', 'page' => 'lost-found'],
        'readmission' => ['label' => 'Re-Admission', 'icon' => '1094', 'page' => 'readmission'],
        'id-replacement' => ['label' => 'ID Replacement', 'icon' => '1091', 'page' => 'id-replacement'],
        'seminar' => ['label' => 'Seminar', 'icon' => '1093', 'page' => 'seminars'],
        'clearance' => ['label' => 'Clearance', 'icon' => '1092', 'page' => 'clearance'],
    ];
    $communityCards = [
        ['Active Students', '1139', $cvuCommunityStats['active'], '/workspace/community'],
        ['For Verifications', '1143', $cvuCommunityStats['verification'], '/workspace/community#attendance-verification'],
        ['Completed This Month', '1150', $cvuCommunityStats['completed'], '/workspace/community'],
    ];
@endphp

@include('workspace.cvu-stat-cards', ['label' => 'Unit activity'])

<div class="cvu-dashboard-grid">
    <section class="cvu-panel" aria-labelledby="cvu-summary-title">
        <h2 id="cvu-summary-title"><img src="/staff-assets/21-1085.svg" alt="">Today’s Summary</h2>
        <ul class="cvu-summary">
            @foreach($services as $kind => $service)
                <li>
                    <a href="/workspace/{{ $service['page'] }}">
                        <img src="/staff-assets/21-{{ $service['icon'] }}.svg" alt="">
                        <span>{{ $service['label'] }}</span>
                        <strong>{{ ($cvuSummary[$kind] ?? 0) + ($kind === 'lost' ? ($cvuSummary['found'] ?? 0) : 0) }}</strong>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cvu-panel" aria-labelledby="cvu-review-title">
        <h2 id="cvu-review-title"><img src="/staff-assets/21-1112.svg" alt="">Items Needing Review</h2>
        <div class="cvu-review-table">
            <table>
                <thead><tr><th scope="col">Type</th><th scope="col">Reference No.</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @forelse($cvuReviewQueue as $item)
                        @php($service = $services[$item->kind === 'found' ? 'lost' : $item->kind])
                        <tr>
                            <td><span class="cvu-review-type"><img src="/staff-assets/21-{{ $service['icon'] }}.svg" alt="">{{ $service['label'] }}</span></td>
                            <td class="cvu-reference">{{ $item->ticket }}</td>
                            <td><a class="cvu-review-link" href="{{ request()->fullUrlWithQuery(['review' => $item->id, 'report' => null]) }}" aria-label="Review {{ $item->ticket }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="cvu-review-empty">No items need review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cvuReviewQueue->hasPages())
            <nav class="cvu-review-pages" aria-label="Review queue pages">
                @if($cvuReviewQueue->previousPageUrl())<a href="{{ $cvuReviewQueue->previousPageUrl() }}">Previous</a>@endif
                @if($cvuReviewQueue->nextPageUrl())<a href="{{ $cvuReviewQueue->nextPageUrl() }}">Next</a>@endif
            </nav>
        @endif
    </section>

    <section class="cvu-panel cvu-community" aria-labelledby="cvu-community-title">
        <h2 id="cvu-community-title"><img src="/staff-assets/21-1153.svg" alt="">Community Service Overview</h2>
        <div class="cvu-stat-grid">
            @foreach($communityCards as [$label, $icon, $value, $url])
                <a class="cvu-stat" href="{{ $url }}">
                    <span class="cvu-stat-icon"><img src="/staff-assets/21-{{ $icon }}.svg" alt=""></span>
                    <div><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
                </a>
            @endforeach
        </div>
    </section>
</div>
