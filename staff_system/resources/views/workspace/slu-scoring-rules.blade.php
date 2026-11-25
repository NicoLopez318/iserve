@php
    $ruleLabels = [
        'event' => 'Completed Approved Event',
        'achievement' => 'Approved Achievement',
        'report' => 'On-Time Completion Report',
        'participant' => 'Verified Participant',
        'penalty' => 'Late Report Deduction',
    ];
    $current = array_replace(['event' => 0, 'achievement' => 100, 'report' => 0, 'participant' => null, 'penalty' => 0], $rules);
    $formatRule = function ($key, $value) {
        if ($value === null) return 'Per event';
        $amount = (int) $value;
        return ($amount > 0 ? ($key === 'penalty' ? '−' : '+') : '').number_format($amount);
    };
    $levels = App\Support\LeadershipPoints::LEVELS;
    $editing = old('_dialog') === 'scoring-rules-edit' && $errors->hasAny(array_keys($ruleLabels));
    $previewUrl = rtrim(config('app.student_portal_url'), '/').'/portal/leadership/leaderboards';
@endphp

<dialog id="scoring-rules" class="slu-scoring-dialog" aria-labelledby="slu-scoring-title"
        @if($editing || session('scoring_rules_saved')) open data-auto-open @endif>
    <button type="button" class="close-dialog" data-close aria-label="Close scoring rules">×</button>
    <section class="slu-scoring-section">
        <h2 id="slu-scoring-title">Scoring Rules</h2>
        <p>Point values for activities and achievements.</p>
        @if(session('scoring_rules_saved'))
            <div class="slu-scoring-saved" role="status">Scoring rules saved.</div>
        @endif
        <dl class="slu-scoring-values">
            @foreach($ruleLabels as $key => $label)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd @class(['slu-points-positive' => $key !== 'penalty' && $current[$key] > 0, 'slu-points-negative' => $key === 'penalty' && $current[$key] > 0])>
                        {{ $formatRule($key, $current[$key]) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <div class="slu-scoring-actions">
            <button type="button" class="sfu-button sfu-outline" data-dialog="scoring-rules-edit">Edit Rules</button>
            <button type="button" class="sfu-button" data-dialog="scoring-rules-history">View Rule History</button>
        </div>
    </section>

    <section class="slu-scoring-section">
        <h3>Leadership Levels</h3>
        <p>Point thresholds for organization rankings.</p>
        <ul class="slu-scoring-levels">
            @foreach($levels as $index => $level)
                @php
                    $next = $levels[$index + 1]['minimum'] ?? null;
                    $range = match (true) {
                        $loop->first => 'Below '.number_format($next),
                        $loop->last => number_format($level['minimum']).'+',
                        default => number_format($level['minimum']).'–'.number_format($next - 1),
                    };
                @endphp
                <li>
                    <span class="slu-scoring-badge is-level-{{ $index }}" aria-hidden="true"></span>
                    <strong>{{ $level['name'] }}</strong>
                    <span>{{ $range }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="slu-scoring-section">
        <h3>Publication Status</h3>
        <p>Readiness of the current leaderboard totals.</p>
        <div class="slu-scoring-release">
            <div>
                <span>Calculation Valid</span>
                <strong @class(['slu-points-positive' => $leadershipPoints['calculationValid'], 'slu-points-negative' => !$leadershipPoints['calculationValid']])>{{ $leadershipPoints['calculationValid'] ? 'Yes' : 'Check totals' }}</strong>
            </div>
            <a href="/workspace/achievements">{{ number_format($scoringPublication['awards']) }} Pending {{ Str::plural('Award', $scoringPublication['awards']) }} Excluded <span aria-hidden="true">›</span></a>
            <a href="#pending-adjustments" data-close>{{ number_format($scoringPublication['adjustments']) }} {{ Str::plural('Adjustment', $scoringPublication['adjustments']) }} Excluded <span aria-hidden="true">›</span></a>
        </div>
        <a class="sfu-button sfu-outline slu-scoring-preview" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview Student Leaderboard</a>
    </section>
</dialog>

<dialog id="scoring-rules-edit" class="slu-scoring-dialog" aria-labelledby="slu-scoring-edit-title"
        @if($editing) open data-auto-open @endif>
    <button type="button" class="close-dialog" data-close aria-label="Close editor">×</button>
    <section class="slu-scoring-section">
        <h2 id="slu-scoring-edit-title">Edit Rules</h2>
        <p>Enter point values from 0 to 1,000. Enter the deduction as a positive amount.</p>
        <form class="slu-scoring-edit" method="post" action="/workspace/points-rules">
            @csrf
            @foreach($ruleLabels as $key => $label)
                <label class="slu-scoring-edit-row">
                    <span>{{ $label }}</span>
                    <input type="number" name="{{ $key }}" min="0" max="1000" required
                           value="{{ is_scalar(old($key)) ? old($key) : ($current[$key] ?? 0) }}"
                           @error($key) aria-invalid="true" aria-describedby="slu-scoring-{{ $key }}-error" @enderror>
                </label>
                @error($key)<p class="errors" id="slu-scoring-{{ $key }}-error" role="alert">{{ $message }}</p>@enderror
            @endforeach
            <div class="slu-scoring-actions">
                <button type="button" class="sfu-button sfu-outline" data-close>Cancel</button>
                <button type="submit" class="sfu-button">Save Rules</button>
            </div>
        </form>
    </section>
</dialog>

<dialog id="scoring-rules-history" class="slu-scoring-dialog" aria-labelledby="slu-scoring-history-title">
    <button type="button" class="close-dialog" data-close aria-label="Close history">×</button>
    <section class="slu-scoring-section">
        <h2 id="slu-scoring-history-title">Rule History</h2>
        <p>Recent saved scoring rule changes.</p>
        <div class="slu-scoring-history">
            @forelse($scoringHistory as $entry)
                @php
                    $snapshot = json_decode($entry->details, true) ?? [];
                    $date = \Illuminate\Support\Carbon::parse($entry->created_at);
                @endphp
                <article>
                    <h3>{{ $entry->name ?? 'Staff account' }}</h3>
                    <time datetime="{{ $date->toAtomString() }}">{{ $date->format('M j, Y g:i A') }}</time>
                    <dl class="slu-scoring-values">
                        @foreach($ruleLabels as $key => $label)
                            @if(array_key_exists($key, $snapshot))
                                <div><dt>{{ $label }}</dt><dd>{{ $formatRule($key, $snapshot[$key]) }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                </article>
            @empty
                <p>No scoring rule changes recorded.</p>
            @endforelse
        </div>
    </section>
</dialog>
