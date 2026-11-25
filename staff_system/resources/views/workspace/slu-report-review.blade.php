@php
    $snapshot = $item->snapshot ?? [];
    $records = collect($snapshot['records'] ?? (array_is_list($snapshot) ? $snapshot : []));
    $metrics = collect($snapshot['activity'] ?? []);
    $files = collect($snapshot['files'] ?? []);
    $month = App\Models\Report::reportingMonth($snapshot['date_from'] ?? null, $snapshot['date_to'] ?? null);
    $period = $month ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format('F Y') : $item->semester.' · '.$item->academic_year;
    $tickets = $records->pluck('ticket')->filter();
    $duplicateCount = $tickets->count() - $tickets->unique()->count();
    $eventSources = $metrics->contains('service', 'Events Completed');
    $attendanceSources = $metrics->contains('service', 'Verified Attendance');
    $sourceChecks = [
        ['Event activity', $eventSources ? 'Snapshot saved' : 'Not recorded', $eventSources],
        ['Attendance records', $attendanceSources ? 'Snapshot saved' : 'Not recorded', $attendanceSources],
        ['Unit submissions', number_format($records->count()).' on record', $records->isNotEmpty()],
        ['Duplicate references', $tickets->isEmpty() ? 'No references to check' : ($duplicateCount ? number_format($duplicateCount).' repeated' : 'None found'), $tickets->isNotEmpty() && !$duplicateCount],
    ];
    [$unit, $assistant, $admin, $activeStage] = match ($item->status) {
        'draft' => ['Draft', 'Pending', 'Pending', 0],
        'needs_revision' => ['Revision required', 'Returned', 'Pending', 0],
        'pending' => ['Submitted', 'Under review', 'Pending', 1],
        'assistant_revision' => ['Submitted', 'Revision required', 'Returned', 1],
        'awaiting_admin' => ['Submitted', 'Forwarded', isset($snapshot['admin_review_started_at']) ? 'Under review' : 'Awaiting review', 2],
        'approved' => ['Submitted', 'Forwarded', 'Acknowledged', 3],
        'rejected' => ['Submitted', 'Forwarded', 'Declined', 2],
        default => [str($item->status)->headline(), 'Pending', 'Pending', 0],
    };
    $editable = $item->user_id === auth()->id() && in_array($item->status, ['draft', 'needs_revision'], true);
    $unitNote = old('unit_note', $snapshot['unit_note'] ?? '');
    $oldSummary = old('summary', $item->summary);
@endphp

<section class="panel slu-report-review">
    <h2 id="slu-report-review-title">Unit Report Review</h2>
    <p class="slu-report-review-name">{{ $item->title }}</p>
    <p class="slu-report-review-period">{{ $period }}</p>
    <p class="slu-report-review-byline">
        Prepared by {{ $item->user->name }}, Unit Head<br>
        Last updated <time datetime="{{ $item->updated_at?->toAtomString() }}">{{ $item->updated_at?->format('M j, Y · g:i A') ?? 'Not recorded' }}</time>
        · Version {{ $snapshot['version'] ?? 1 }}
    </p>

    <h3>Report Contents</h3>
    <div class="slu-report-contents">
        <dl>
            @foreach($metrics as $metric)
                <div>
                    <dt>{{ $metric['service'] }}</dt>
                    <dd>
                        {{ number_format($metric['completed'] ?? 0) }}
                        @if(!empty($metric['pending']))<small>{{ number_format($metric['pending']) }} pending at period end</small>@endif
                    </dd>
                </div>
            @endforeach
            <div><dt>Source Records</dt><dd>{{ number_format($records->count()) }}</dd></div>
        </dl>
        <dl>
            <div>
                <dt>Supporting Files</dt>
                <dd @class(['slu-points-positive' => $files->isNotEmpty()])>{{ $files->isNotEmpty() ? $files->count().' attached' : 'None attached' }}</dd>
            </div>
            @foreach($files as $index => $file)
                <div>
                    <dt>{{ $file['name'] }}</dt>
                    <dd><a href="/workspace/report/{{ $item->id }}/file/{{ $index }}" aria-label="Download {{ $file['name'] }}">Download</a></dd>
                </div>
            @endforeach
            <div><dt>Full Source Snapshot</dt><dd><a href="/workspace/report/{{ $item->id }}">View</a></dd></div>
        </dl>
    </div>

    <div class="slu-report-validation">
        <section>
            <h3>Source Validation</h3>
            <dl>
                @foreach($sourceChecks as [$label, $value, $confirmed])
                    <div>
                        <dt>@if($confirmed)<img src="/staff-assets/21-4770.svg" alt="">@endif{{ $label }}</dt>
                        <dd @class(['slu-points-positive' => $confirmed])>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section>
            <h3>Approval Route</h3>
            <ol class="slu-report-approval-route">
                @foreach(['Unit Head' => $unit, 'Assistant' => $assistant, 'Admin' => $admin] as $stage => $state)
                    <li @class(['is-active' => $loop->index === $activeStage, 'is-past' => $loop->index < $activeStage])
                        @if($loop->index === $activeStage) aria-current="step" @endif>
                        <span>{{ $stage }}</span><small>{{ $state }}</small>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    @if($item->review_note)
        <h3>Reviewer Feedback</h3><p class="text-body">{{ $item->review_note }}</p>
    @endif
    @if($editable)
        <form class="slu-report-review-form" method="post" action="/workspace/report/{{ $item->id }}" enctype="multipart/form-data">
            @csrf
            <label for="slu-unit-report-note">Unit Head Note</label>
            <textarea id="slu-unit-report-note" name="unit_note" maxlength="3000"
                      placeholder="Add notes for the Assistant (optional)">{{ is_scalar($unitNote) ? $unitNote : '' }}</textarea>
            @error('unit_note')<p class="errors" role="alert">{{ $message }}</p>@enderror
            <details @if($errors->hasAny(['summary','attachments','attachments.*']) || mb_strlen(is_string($oldSummary) ? $oldSummary : '') < 30) open @endif>
                <summary>Report Summary and Files</summary>
                <label class="field">Summary<textarea name="summary" required minlength="30" maxlength="5000">{{ is_scalar($oldSummary) ? $oldSummary : '' }}</textarea></label>
                @error('summary')<p class="errors" role="alert">{{ $message }}</p>@enderror
                <label class="field">Replace Supporting Files<input type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.xlsx,.csv,.docx"></label>
                @foreach(array_merge($errors->get('attachments'), ...array_values($errors->get('attachments.*'))) as $message)
                    <p class="errors" role="alert">{{ $message }}</p>
                @endforeach
            </details>
            <div class="slu-report-review-actions">
                <button type="submit" class="sfu-button sfu-outline" name="status" value="draft">Save Draft</button>
                <button type="submit" class="sfu-button" name="status" value="pending">Submit to Assistant</button>
            </div>
            <p class="slu-report-review-help">Submission locks the current version. Editing a returned report creates a new version.</p>
        </form>
    @else
        @if(filled($snapshot['unit_note'] ?? null))<h3>Unit Head Note</h3><p class="text-body">{{ $snapshot['unit_note'] }}</p>@endif
        <details><summary>Report Summary</summary><p class="text-body">{{ $item->summary }}</p></details>
    @endif
    <details class="slu-report-history">
        <summary>Submission Timeline</summary>
        <div class="timeline">
            @forelse($history as $entry)
                <article><strong>{{ str($entry->action)->after('report.')->headline() }}</strong><p>{{ $entry->details }}</p><small>{{ $entry->name }} · {{ $entry->created_at }}</small></article>
            @empty
                <p>No report activity recorded.</p>
            @endforelse
        </div>
    </details>
    <a class="slu-report-print" href="/workspace/report/{{ $item->id }}/receipt" target="_blank" rel="noopener">{{ $item->status === 'approved' ? 'Download Receipt' : 'Preview / Print Report' }}</a>
</section>
