@php
    $details = $item->details ?? [];
    $applicant = $details['applicant'] ?? [];
    $organization = $organizations->find($details['organization_id'] ?? $item->user->organization_id);
    $submitter = implode(', ', array_filter([$applicant['name'] ?? $item->user->name, $applicant['position'] ?? $item->user->position]));
    $budget = $details['budget'] ?? null;
    if (is_numeric($budget)) {
        $budget = '₱'.number_format((float) $budget, floor((float) $budget) == (float) $budget ? 0 : 2);
    }
    $startsAt = empty($details['starts_at']) ? null : rescue(fn () => \Illuminate\Support\Carbon::parse($details['starts_at']), report: false);
    $endsAt = empty($details['ends_at']) ? null : rescue(fn () => \Illuminate\Support\Carbon::parse($details['ends_at']), report: false);
    $facts = [
        'Event Date' => $startsAt?->format('M j, Y'),
        'Venue' => $details['venue'] ?? null,
        'Expected Participants' => $details['capacity'] ?? null,
        'Budget' => $budget,
    ];
    $checklist = App\Models\ServiceRequest::PROPOSAL_CHECKLIST;
    $documentLabels = config('portal.documents.activity');
    $additionalFiles = array_diff_key($item->files ?? [], array_flip(array_merge(...array_values($checklist))));
@endphp

<h2 id="slu-proposal-review-title">{{ $reviewActions ? 'Proposal Review' : 'Proposal Details' }}</h2>
<div class="slu-proposal-review-identity">
    <strong>{{ $details['title'] ?? $item->ticket }}</strong>
    <p>{{ $organization ? $organization->name.' · ' : '' }}Submitted by {{ $submitter }}</p>
</div>
<dl class="slu-proposal-facts">
    @foreach($facts as $label => $value)
        <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : 'Not recorded' }}</dd></div>
    @endforeach
</dl>

<h3>Requirement Documents</h3>
@foreach($checklist as $group => $keys)
    <section class="slu-proposal-document-group">
        <h4>{{ $group }}</h4>
        <ul class="slu-review-checklist">
            @foreach($keys as $key)
                <li>@include('workspace.slu-review-attachment', ['label' => $documentLabels[$key], 'unattachedLabel' => 'Not attached'])</li>
            @endforeach
        </ul>
    </section>
@endforeach

@if($additionalFiles)
    <details>
        <summary>Additional Attachments</summary>
        <ul class="slu-review-checklist">
            @foreach($additionalFiles as $key => $file)
                <li>@include('workspace.slu-review-attachment', ['label' => $documentLabels[$key] ?? str($key)->headline()])</li>
            @endforeach
        </ul>
    </details>
@endif

@if($reviewActions)
    <form class="proposal-decision" method="post" action="/staff/requests/{{ $item->id }}">
        @csrf
        @error('workflow')<p class="errors" role="alert">{{ $message }}</p>@enderror
        <label for="slu-proposal-remarks">Reviewer Remarks</label>
        <textarea id="slu-proposal-remarks" name="review_note" minlength="5" maxlength="3000"
                  placeholder="Add remarks (optional for endorsement)" aria-describedby="slu-proposal-remarks-help">{{ old('review_note') }}</textarea>
        <p class="slu-proposal-remarks-help" id="slu-proposal-remarks-help">Remarks are required for all other decisions.</p>
        @error('review_note')<p class="errors" role="alert">{{ $message }}</p>@enderror
        <div class="slu-proposal-decisions">
            @if(isset($reviewActions['needs_revision']))
                <button type="submit" class="sfu-button sfu-outline" name="status" value="needs_revision">Return for Revision</button>
            @endif
            @if(isset($reviewActions['unit_approved']))
                <button type="submit" class="sfu-button" name="status" value="unit_approved">Endorse to Assistant</button>
            @endif
        </div>
        @if(isset($reviewActions['in_review']) || isset($reviewActions['rejected']))
            <details @if(in_array(old('status'), ['in_review', 'rejected'], true) && $errors->has('review_note')) open @endif>
                <summary>More Decisions</summary>
                <div class="slu-proposal-decisions">
                    @if(isset($reviewActions['in_review']))
                        <button type="submit" class="sfu-button sfu-outline" name="status" value="in_review">Start Unit Review</button>
                    @endif
                    @if(isset($reviewActions['rejected']))
                        <button type="submit" class="sfu-button sfu-outline" name="status" value="rejected">Reject Proposal</button>
                    @endif
                </div>
            </details>
        @endif
    </form>
@endif

@if($item->review_note || !$reviewActions)
    <h3>Saved Reviewer Remarks</h3>
    <p class="text-body">{{ $item->review_note ?? 'No remarks recorded.' }}</p>
@endif

<details class="slu-proposal-more" @if(!empty($details['revision'])) open @endif>
    <summary>Submission Details</summary>
    <dl class="slu-proposal-facts">
        <div><dt>Reference No.</dt><dd>{{ $item->ticket }}</dd></div>
        <div><dt>Status</dt><dd>{{ str($item->status)->headline() }}</dd></div>
        @if($startsAt)<div><dt>Starts</dt><dd>{{ $startsAt->format('M j, Y g:i A') }}</dd></div>@endif
        @if($endsAt)<div><dt>Ends</dt><dd>{{ $endsAt->format('M j, Y g:i A') }}</dd></div>@endif
        @if(isset($details['points']))<div><dt>Participation Points</dt><dd>{{ $details['points'] }}</dd></div>@endif
    </dl>
    @if(!empty($details['description']))
        <h3>Activity Description</h3><p class="text-body">{{ $details['description'] }}</p>
    @endif
    @if(!empty($details['revision']))
        <h3>Applicant’s Revision</h3><p class="text-body">{{ $details['revision'] }}</p>
    @endif
</details>
