@php
    $details = $item->details ?? [];
    $applicant = $details['applicant'] ?? [];
    $organization = $organizations->find($details['organization_id'] ?? $item->user->organization_id);
    $currentOrganization = $organizations->find($item->user->organization_id);
    $submitter = implode(', ', array_filter([$applicant['name'] ?? $item->user->name, $applicant['position'] ?? $item->user->position]));
    $date = empty($details['achievement_date']) ? null : rescue(fn () => \Illuminate\Support\Carbon::parse($details['achievement_date']), report: false);
    $files = collect($item->files ?? [])->filter(fn ($file, $key) => $item->attachmentPath($key) !== null);
    $eligible = $organization && $currentOrganization && $organization->is($currentOrganization);
    $dateRecorded = $date && $date->lte(today()->endOfDay());
    $checks = [
        ['Organization eligibility', $eligible ? 'Membership on record' : 'Check membership', $eligible],
        ['Achievement date', $dateRecorded ? 'Date on record' : ($date ? 'Future date' : 'Not recorded'), $dateRecorded],
        ['Evidence authenticity', $files->isEmpty() ? 'No files attached' : 'Manual verification', false],
        ['Duplicate check', match($achievementMatch) { true => 'Possible match', false => 'No matching submission', default => 'Check manually' }, $achievementMatch === false],
        ['Category', $details['category'] ?? 'Not recorded', false],
    ];
    $approved = $item->status === 'approved';
    $closed = in_array($item->status, ['rejected', 'cancelled'], true);
    $visibility = $approved ? 'Listed' : ($closed ? 'Unpublished' : 'After final approval');
    $awarded = $points->firstWhere('source', 'achievement:'.$item->id)?->amount;
    $recommended = $details['awarded_points'] ?? $rules['achievement'] ?? 100;
    $input = old('awarded_points', $recommended);
    $input = is_scalar($input) ? $input : $recommended;
    $shownPoints = is_numeric($input) ? (int) $input : (int) $recommended;
    $formatPoints = fn ($amount) => ($amount > 0 ? '+' : '').number_format($amount).' points';
    $impact = match (true) {
        $awarded !== null => $formatPoints($awarded).' awarded',
        $approved || $closed => 'No award recorded',
        default => $formatPoints($shownPoints).' after final approval',
    };
@endphp

<h2 id="slu-achievement-review-title">{{ $reviewActions ? 'Achievement Review' : 'Achievement Details' }}</h2>
<div class="slu-achievement-review-identity">
    <strong>{{ $details['title'] ?? $item->ticket }}</strong>
    <p>@if($organization)<b>{{ $organization->name }}</b>@endif <span>Submitted by {{ $submitter }}</span></p>
</div>
<dl class="slu-achievement-review-facts">
    @foreach(['Achievement Date' => $date?->format('M j, Y'), 'Venue' => $details['venue'] ?? null, 'Award Received' => $details['award'] ?? null,
              'Description' => $details['description'] ?? null] as $label => $value)
        <dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : 'Not recorded' }}</dd>
    @endforeach
</dl>

<h3>Supporting Documentation</h3>
<ul class="slu-achievement-documents">
    @forelse($files as $key => $file)
        @php($filename = basename(is_array($file) ? ($file['name'] ?? $file['path']) : $file))
        <li>
            <span class="slu-review-document"><img src="/staff-assets/21-5492.svg" alt="">{{ $filename }}</span>
            <a class="sfu-button" href="{{ route('staff.requests.attachment-view', ['serviceRequest' => $item, 'key' => $key]) }}"
               target="_blank" rel="noopener" aria-label="View {{ $filename }}">View</a>
        </li>
    @empty
        <li class="slu-achievement-row-muted">No supporting files attached.</li>
    @endforelse
</ul>

<h3>Verification Checklist</h3>
<dl class="slu-achievement-review-list">
    @foreach($checks as [$label, $value, $confirmed])
        <div>
            <dt>@if($confirmed)<img src="/staff-assets/21-4770.svg" alt="">@endif{{ $label }}</dt>
            <dd @class(['is-confirmed' => $confirmed])>{{ $value }}</dd>
        </div>
    @endforeach
</dl>
@if(!empty($details['revision']))
    <details><summary>Applicant’s Revision</summary><p class="text-body">{{ $details['revision'] }}</p></details>
@endif

@if($reviewActions)
<form class="slu-achievement-decision" method="post" action="/staff/requests/{{ $item->id }}"
      data-achievement-review data-default-points="{{ $recommended }}">
    @csrf
    @error('workflow')<p class="errors" role="alert">{{ $message }}</p>@enderror
    <div class="slu-achievement-points">
        <label for="slu-achievement-points">Recommended Points</label>
        <input id="slu-achievement-points" type="number" name="awarded_points" min="-1000" max="1000"
               value="{{ $input }}" aria-describedby="slu-achievement-points-help">
    </div>
    @error('awarded_points')<p class="errors" role="alert">{{ $message }}</p>@enderror
@endif
    <h3>Portal Impact</h3>
    <dl class="slu-achievement-review-list">
        <div><dt>Organization Achievements</dt><dd>{{ $currentOrganization ? $visibility : 'No current organization' }}</dd></div>
        <div><dt>Student Profile</dt><dd>{{ $visibility }}</dd></div>
        <div><dt>Leaderboard</dt><dd class="is-confirmed" @if($reviewActions) data-achievement-impact aria-live="polite" @endif>{{ $impact }}</dd></div>
    </dl>
@if($reviewActions)
    <label class="slu-achievement-notes-label" for="slu-achievement-notes">Reviewer Notes</label>
    <textarea id="slu-achievement-notes" name="review_note" minlength="5" maxlength="3000"
              placeholder="Add review notes (optional for endorsement)" aria-describedby="slu-achievement-points-help">{{ old('review_note') }}</textarea>
    <p class="slu-achievement-row-muted" id="slu-achievement-points-help">Notes are required for decisions other than endorsement.</p>
    @error('review_note')<p class="errors" role="alert">{{ $message }}</p>@enderror
    <div class="slu-achievement-decisions">
        @if(isset($reviewActions['needs_revision']))
            <button type="submit" class="sfu-button sfu-outline" name="status" value="needs_revision">Return for Evidence</button>
        @endif
        @if(isset($reviewActions['unit_approved']))
            <button type="submit" class="sfu-button" name="status" value="unit_approved" data-achievement-endorse>Endorse for {{ number_format($shownPoints) }} Points</button>
        @endif
    </div>
    @if(isset($reviewActions['in_review']) || isset($reviewActions['rejected']))
        <details @if(in_array(old('status'), ['in_review', 'rejected'], true) && $errors->has('review_note')) open @endif>
            <summary>More Decisions</summary>
            <div class="slu-achievement-decisions">
                @if(isset($reviewActions['in_review']))
                    <button type="submit" class="sfu-button sfu-outline" name="status" value="in_review">Start Unit Review</button>
                @endif
                @if(isset($reviewActions['rejected']))
                    <button type="submit" class="sfu-button sfu-outline" name="status" value="rejected">Reject Achievement</button>
                @endif
            </div>
        </details>
    @endif
</form>
@endif
@if($item->review_note || !$reviewActions)
    <h3>Saved Reviewer Notes</h3>
    <p class="text-body">{{ $item->review_note ?? 'No review yet.' }}</p>
@endif
