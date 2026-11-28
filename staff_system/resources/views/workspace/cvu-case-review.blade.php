@php
    $details = $item->details ?? [];
    $isLostFound = $cvuLostFoundReview ?? false;
    $reviewActions = $item->reviewActions(auth()->user());
    $statuses = $isLostFound ? App\Models\ServiceRequest::LOST_FOUND_STATUSES : App\Models\ServiceRequest::COMPLAINT_STATUSES;
    $status = $isLostFound ? $item->lostFoundStatus() : $item->status;
    $currentStatus = $status === 'pending' ? 'For Review' : ($statuses[$status] ?? str($status)->headline());
    $actionField = $isLostFound ? 'lost_found_action' : 'complaint_action';
    $evidenceTitle = $isLostFound ? 'Supporting File' : 'Supporting Evidence';
    $description = trim($details['description'] ?? '');
    $incident = trim(($details['incident_date'] ?? '').' '.($details['incident_time'] ?? ''));
    if (!empty($details['incident_date'])) {
        try {
            $incident = \Carbon\Carbon::parse($incident)->format(empty($details['incident_time']) ? 'M j, Y' : 'M j, Y, g:i A');
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            // Retain the submitted value when an older record uses an unrecognized format.
        }
    }
    $facts = $isLostFound ? [
        ['Reference Number', $item->ticket],
        ['Student ID', $item->user->student_id],
        ['Item Name', $details['item_name'] ?? null],
        ['Report Type', App\Models\ServiceRequest::LOST_FOUND_TYPES[$item->kind]],
        ['Date & Time Reported', $item->created_at->format('M j, Y, g:i A')],
        ['Reporter / Finder', $item->user->name],
    ] : [
        ['Reference Number', $item->ticket],
        ['Student ID', $item->user->student_id],
        ['Type of Complaint', $item->complaintType()],
        ['Date & Time of Incident', $incident],
        ['Location of Incident', $details['location'] ?? null],
        ['Respondent Complained Against', $details['respondent'] ?? null],
        ['Witness', $details['witness'] ?? null],
    ];
    $scheduleOpen = old('complaint_action') === 'schedule';
@endphp

<section class="cvu-case-review">
    <h2 id="cvu-case-review-title" tabindex="-1" autofocus>{{ $isLostFound ? 'Selected Item Details' : 'Complaints Details' }}</h2>
    <div class="cvu-case-review-body">
        @if($isLostFound && session('success'))
            <div class="notice" role="status">{{ session('success') }}</div>
        @endif
        <dl class="cvu-case-facts">
            @foreach($facts as [$label, $value])
                <div @class(['cvu-fact-wide' => $loop->index >= 4])>
                    <dt>{{ $label }}</dt>
                    <dd>{{ filled($value) ? $value : '—' }}</dd>
                </div>
            @endforeach
        </dl>

        @include('workspace.cvu-case-description')
        @include('workspace.cvu-case-attachments', ['evidenceEmpty' => $isLostFound ? 'No supporting file attached.' : 'No supporting evidence attached.'])

        @if(!$isLostFound)
            @if(filled($details['interview_at'] ?? null))
                <p class="cvu-complaint-interview">Scheduled {{ strtolower($details['meeting_type'] ?? 'Interview') }} <strong>{{ $details['interview_at'] }}</strong></p>
            @endif
            @include('workspace.complaint-parent-guardian')
        @endif
        @if($reviewActions)
            <form method="post" action="{{ url('/workspace/request/'.$item->id.'/note') }}" class="cvu-case-decision">
                @csrf
                <button type="submit" name="{{ $actionField }}" value="save" hidden>Save Review</button>
                <div class="cvu-case-decision-fields">
                    <label>Case Status
                        <select name="status">
                            <option value="">{{ $currentStatus }}</option>
                            @foreach($isLostFound ? ['rejected'] : ['unit_approved', 'rejected'] as $decision)
                                @if(isset($reviewActions[$decision]))
                                    <option value="{{ $decision }}" @selected(old('status') === $decision)>{{ $reviewActions[$decision] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <label>Unit Head Remarks
                        <textarea name="review_note" required minlength="5" maxlength="3000" rows="2" placeholder="Write your remarks here…">{{ old('review_note', $item->review_note) }}</textarea>
                    </label>
                </div>
                @foreach(['review_note', 'status', 'workflow'] as $field)
                    @error($field)<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
                @endforeach
                <div class="cvu-case-decision-actions">
                    <button type="submit" name="{{ $actionField }}" value="request-info" class="cvu-review-action">Request Info</button>
                    @if($isLostFound)
                        <button type="submit" name="{{ $actionField }}" value="save" class="cvu-review-action">Save Review</button>
                        <button type="submit" name="{{ $actionField }}" value="validate" class="cvu-review-action cvu-review-primary">Validate Claim</button>
                    @else
                        <details class="cvu-complaint-schedule" @if($scheduleOpen) open @endif>
                            <summary class="cvu-review-action cvu-review-primary">Schedule Interview or Case Conference</summary>
                            <label>Meeting Type
                                <select name="meeting_type">
                                    @foreach(['Interview','Case Conference'] as $meetingType)
                                        <option @selected(old('meeting_type', $details['meeting_type'] ?? 'Interview') === $meetingType)>{{ $meetingType }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @error('meeting_type')<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
                            <label>Meeting Date &amp; Time
                                <input type="datetime-local" name="interview_at" value="{{ old('interview_at') }}">
                            </label>
                            @error('interview_at')<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
                            <button type="submit" name="complaint_action" value="schedule" class="cvu-review-action">Save Schedule</button>
                        </details>
                        <button type="submit" name="complaint_action" value="save" class="cvu-review-action">Save Review</button>
                        <button type="button" class="cvu-review-action" title="Final approval requires Assistant forwarding and Admin approval." disabled>Finalize Case</button>
                    @endif
                </div>
            </form>
        @else
            <dl class="cvu-case-decision-fields cvu-case-facts">
                <div><dt>Case Status</dt><dd>{{ $currentStatus }}</dd></div>
                <div><dt>Unit Head Remarks</dt><dd>{{ $item->review_note ?: '—' }}</dd></div>
            </dl>
        @endif

        @if(!$isLostFound)
            @if($item->status === 'approved')
                <a href="/workspace/community" class="cvu-review-action cvu-community-action">Proceed to Community Service</a>
            @else
                <button type="button" class="cvu-review-action cvu-community-action" disabled>Proceed to Community Service</button>
            @endif
        @elseif(filled($incident) || filled($details['location'] ?? null) || filled($details['contact'] ?? null))
            <details class="cvu-lost-found-details">
                <summary>More Report Details</summary>
                <dl class="cvu-case-facts">
                    @foreach(['Incident Date' => $incident, 'Location' => $details['location'] ?? null, 'Contact' => $details['contact'] ?? null] as $label => $value)
                        @if(filled($value))
                            <div class="cvu-fact-wide"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endif
                    @endforeach
                </dl>
            </details>
        @endif

        @if(in_array($item->status, ['approved', 'rejected', 'cancelled'], true))
            @include('workspace.review-record-actions')
        @endif
        @include('workspace.review-history', ['showSubmitter' => true])
    </div>
</section>
