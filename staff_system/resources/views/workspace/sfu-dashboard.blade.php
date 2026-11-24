@php
    $totals = [
        'Received' => $sfuSummary['received'],
        'Compliance Applications' => $sfuSummary['received'] - $sfuSummary['incomplete'],
        'Processed' => $sfuSummary['processed'],
    ];
    $term = implode(' ', array_filter([$sfuSummary['semester'], $sfuSummary['year'] ? 'AY '.$sfuSummary['year'] : null]));
@endphp

@include('workspace.unit-stat-cards')

<div class="sfu-grid">
    @include('workspace.sfu-application-queue')

    @include('workspace.sfu-review-panel')

    <section class="sfu-panel sfu-summary" aria-labelledby="sfu-summary-title">
        <h2 id="sfu-summary-title">Semester Summary</h2>
        <div class="sfu-summary-grid">
            @foreach($totals as $label => $total)
                @php
                    $percent = $sfuSummary['received'] ? (int) round($total / $sfuSummary['received'] * 100) : 0;
                @endphp
                <div class="sfu-summary-item">
                    <span>{{ $label }}</span><strong>{{ $total }}</strong>
                    <progress value="{{ $percent }}" max="100" aria-label="{{ $label }} share"></progress><small>{{ $percent }}%</small>
                </div>
            @endforeach
            <div class="sfu-summary-item"><span>Average Processing Time</span><strong>{{ $sfuSummary['processing_days'] === null ? '—' : $sfuSummary['processing_days'].' '.str('day')->plural($sfuSummary['processing_days']) }}</strong></div>
        </div>
    </section>

    <section class="sfu-panel sfu-report" aria-labelledby="sfu-report-title">
        <h2 id="sfu-report-title">Semester Report</h2>
        <p class="sfu-report-term">{{ $term ?: 'No semester configured' }}</p>
        <dl class="sfu-report-totals">
            @foreach(['processed' => 'Processed', 'incomplete' => 'Incomplete', 'in_progress' => 'In Progress'] as $key => $label)
                <div><dt>{{ $label }}</dt><dd>{{ $sfuSummary[$key] }}</dd></div>
            @endforeach
        </dl>
        <button class="sfu-button" type="button" data-dialog="prepare-report"><img src="/staff-assets/21-4850.svg" alt="">Prepare Semester Report</button>
    </section>
</div>
