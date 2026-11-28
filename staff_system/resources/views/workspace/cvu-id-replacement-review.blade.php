@php
    $details = $item->details ?? [];
    $student = $item->user;
    $identity = [
        'Reference Number' => $item->ticket,
        'Student ID' => $student->student_id,
        'Student Name' => $details['student_name'] ?? $student->name,
        'Course' => $student->course,
        'Year' => $student->year_level,
        'Contact' => $student->phone,
    ];
    $reason = $details['replacement_reason'] ?? null;
    $incidentDate = strtotime($details['incident_date'] ?? '');
    $incident = [
        'Reason for replacement' => $reason,
        'Last seen location' => $details['last_seen_location'] ?? null,
        ($reason === 'Lost' ? 'Date lost' : 'Incident date') => $incidentDate === false ? null : date('M j, Y', $incidentDate),
    ];
    $term = trim(($details['semester'] ?? '').(filled($details['academic_year'] ?? null) ? ' AY '.$details['academic_year'] : ''));
    $reviewActions = $item->reviewActions(auth()->user());
    $status = $item->idReplacementQueueStatus();
    $currentStatus = App\Models\ServiceRequest::ID_REPLACEMENT_STATUSES[$status] ?? str($status)->headline();
    $remarks = old('review_note', $item->review_note);
@endphp

<section class="cvu-case-review">
    <h2 id="cvu-id-replacement-review-title" tabindex="-1" autofocus>Selected Request Details</h2>
    <div class="cvu-case-review-body">
        @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
        <dl class="cvu-case-facts">
            @foreach($identity as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
            @endforeach
        </dl>
        <dl class="cvu-case-facts cvu-id-replacement-incident">
            @foreach($incident as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
            @endforeach
            <div class="cvu-fact-wide cvu-id-replacement-term">
                <dt>Semester &amp; Academic Year</dt>
                <dd>{{ filled($term) ? $term : '—' }}</dd>
            </div>
            @if(filled($details['receipt_number'] ?? null) || filled($details['payment_amount'] ?? null))
                <div><dt>Receipt Number</dt><dd>{{ $details['receipt_number'] ?? '—' }}</dd></div>
                <div><dt>Payment Amount</dt><dd>{{ $details['payment_amount'] ?? '—' }}</dd></div>
            @endif
        </dl>

        @include('workspace.cvu-case-description', ['description' => trim($details['description'] ?? ''), 'descriptionTitle' => 'Additional Details'])
        @include('workspace.cvu-case-attachments', ['evidenceTitle' => 'Supporting File', 'evidenceEmpty' => 'No file attached.'])

        <form method="post" action="{{ url('/workspace/request/'.$item->id.'/note') }}" class="cvu-case-decision">
            @csrf
            <div class="cvu-case-decision-fields">
                <label>Case Status
                    <select name="status" @disabled(!$reviewActions)>
                        <option value="">{{ $currentStatus }}</option>
                        @if(isset($reviewActions['rejected']))
                            <option value="rejected" @selected(old('status') === 'rejected')>{{ $reviewActions['rejected'] }}</option>
                        @endif
                    </select>
                </label>
                <label>Unit Head Remarks
                    <textarea name="review_note" minlength="5" maxlength="3000" rows="2" required @readonly(!$reviewActions)
                        placeholder="Write your remarks here…">{{ is_string($remarks) ? $remarks : '' }}</textarea>
                </label>
            </div>
            @foreach(['review_note','status','id_replacement_action','workflow'] as $field)
                @error($field)<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
            @endforeach
            <div class="cvu-case-decision-actions">
                <button type="submit" name="id_replacement_action" value="request-info" class="cvu-review-action" @disabled(!isset($reviewActions['needs_revision']))>Request Info</button>
                <button type="submit" name="id_replacement_action" value="approve" class="cvu-review-action @if(isset($reviewActions['unit_approved'])) cvu-review-primary @endif" @disabled(!isset($reviewActions['unit_approved']))>Approve Request</button>
                <button type="submit" name="id_replacement_action" value="save" class="cvu-review-action" @disabled(!$reviewActions)>Save Review</button>
                @if($item->status === 'approved')
                    <a class="cvu-review-action" href="{{ url('/workspace/request/'.$item->id.'/slip') }}" target="_blank" rel="noopener">Print Slip</a>
                @else
                    <button type="button" class="cvu-review-action" title="Available after final approval." disabled>Print Slip</button>
                @endif
            </div>
        </form>
        @if(in_array($item->status, ['approved', 'rejected', 'cancelled'], true))
            @include('workspace.review-record-actions')
        @endif
        @if($history->isNotEmpty())
            @include('workspace.review-history', ['showSubmitter' => true])
        @endif
    </div>
</section>
