@php
    $details = $item->details ?? [];
    $applicant = $details['applicant'] ?? [];
    $identity = [
        'Reference Number' => $item->ticket,
        'Student ID' => $applicant['student_id'] ?? $item->user->student_id,
        'Student Name' => $applicant['name'] ?? $item->user->name,
        'Course' => $applicant['course'] ?? $item->user->course,
        'Year' => $applicant['year_level'] ?? $item->user->year_level,
        'Contact' => $applicant['contact'] ?? $item->user->phone,
    ];
    $subject = [
        'Instructor' => $details['instructor'] ?? null,
        'Subject' => $details['subject'] ?? null,
        'Subject Code' => $details['subject_code'] ?? null,
        'Subject Schedule' => $details['schedule'] ?? null,
        'Semester & Academic Year' => trim(($details['semester'] ?? '').(filled($details['academic_year'] ?? null) ? ' AY '.$details['academic_year'] : '')),
    ];
    $reviewActions = $item->reviewActions(auth()->user());
    $status = $item->readmissionQueueStatus();
    $currentStatus = $status === 'pending' ? 'For Review' : (App\Models\ServiceRequest::READMISSION_STATUSES[$status] ?? str($status)->headline());
    $remarks = old('review_note', $item->review_note);
@endphp

<section class="cvu-case-review">
    <h2 id="cvu-readmission-review-title" tabindex="-1" autofocus>Selected Request Details</h2>
    <div class="cvu-case-review-body">
        @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
        <dl class="cvu-case-facts">
            @foreach($identity as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
            @endforeach
        </dl>
        <dl class="cvu-case-facts cvu-readmission-subject">
            @foreach($subject as $label => $value)
                <div @class(['cvu-fact-wide' => $loop->last])><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
            @endforeach
        </dl>

        @include('workspace.cvu-case-description', ['description' => trim($details['description'] ?? ''), 'descriptionTitle' => 'Explanation'])
        @include('workspace.cvu-case-attachments', ['evidenceTitle' => 'Attached File', 'evidenceEmpty' => 'No file attached.'])

        @if(filled($details['interview_at'] ?? null))
            <p class="cvu-complaint-interview">Scheduled interview <strong>{{ str_replace('T', ' ', $details['interview_at']) }}</strong></p>
        @endif
        <form method="post" action="{{ url('/workspace/request/'.$item->id.'/note') }}" class="cvu-case-decision">
            @csrf
            <div class="cvu-case-decision-fields">
                <label>Case Status
                    <select name="status" @disabled(!$reviewActions)>
                        <option value="">{{ $currentStatus }}</option>
                        @foreach(['unit_approved','rejected'] as $decision)
                            @if(isset($reviewActions[$decision]))
                                <option value="{{ $decision }}" @selected(old('status') === $decision)>{{ $reviewActions[$decision] }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label>Unit Head Remarks
                    <textarea name="review_note" minlength="5" maxlength="3000" rows="2" required @readonly(!$reviewActions)
                        placeholder="Write your remarks here…">{{ is_string($remarks) ? $remarks : '' }}</textarea>
                </label>
            </div>
            @foreach(['review_note','status','workflow'] as $field)
                @error($field)<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
            @endforeach
            <div class="cvu-case-decision-actions">
                <button type="submit" name="readmission_action" value="request-info" class="cvu-review-action" @disabled(!$reviewActions)>Request Info</button>
                @if($reviewActions)
                    <a class="cvu-review-action cvu-review-primary" href="{{ request()->fullUrlWithQuery(['panel'=>'interview']) }}">Schedule Interview</a>
                @else
                    <button type="button" class="cvu-review-action" disabled>Schedule Interview</button>
                @endif
                <button type="submit" name="readmission_action" value="save" class="cvu-review-action" @disabled(!$reviewActions)>Save Review</button>
                @if($item->status === 'approved')
                    <a class="cvu-review-action" href="{{ url('/workspace/request/'.$item->id.'/slip') }}" target="_blank" rel="noopener">Issue Slip</a>
                @else
                    <button type="button" class="cvu-review-action" title="Available after final approval." disabled>Issue Slip</button>
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
