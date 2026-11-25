@php
    $details = $item->details ?? [];
    $applicant = $details['applicant'] ?? [];
    $organization = $organizations->find($details['organization_id'] ?? null);
    $studentInformation = [
        'Student ID' => $applicant['student_id'] ?? $item->user->student_id,
        'Program' => $applicant['course'] ?? $item->user->course,
        'Year Level' => $applicant['year_level'] ?? $item->user->year_level,
        'Enrollment Status' => $applicant['enrollment_status'] ?? $details['enrollment_status'] ?? null,
    ];
    $applicationInformation = [
        'Organization' => $organization?->name,
        'Requested Position' => $details['position'] ?? null,
        'Academic Year' => $details['academic_year'] ?? null,
        'Submitted' => $item->created_at->format('M j, Y'),
    ];
    $requirements = array_replace(config('portal.documents.leadership'), [
        'endorsement' => 'Endorsement Letter',
        'candidacy' => 'Certificate of Candidacy',
        'student_card' => 'Scanned Copy of ID',
        'registration' => 'Copy of Enrollment Form',
    ]);
    $files = $item->files ?? [];
    $otherAttachments = array_diff_key($files, $requirements);
    $status = App\Models\ServiceRequest::MEMBERSHIP_STATUSES[$item->status] ?? str($item->status)->headline();
@endphp

<h2 id="slu-membership-review-title">Leadership Application Review</h2>
<div class="slu-membership-review-header">
    <div>
        <strong>{{ $applicant['name'] ?? $item->user->name }}</strong>
        <span>{{ $item->ticket }}</span>
    </div>
    <span class="slu-membership-badge slu-membership-status is-{{ $item->status }}">{{ $status }}</span>
</div>

@foreach(['Student Information' => $studentInformation, 'Application Details' => $applicationInformation] as $heading => $fields)
    <section class="slu-membership-review-section">
        <h3>{{ $heading }}</h3>
        <dl>
            @foreach($fields as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ filled($value) ? $value : 'Not recorded' }}</dd>
            @endforeach
        </dl>
    </section>
@endforeach
@if(filled($details['reason'] ?? null) || filled($details['revision'] ?? null))
    <details>
        <summary>Applicant Notes</summary>
        @if(filled($details['reason'] ?? null))<p class="text-body">{{ $details['reason'] }}</p>@endif
        @if(filled($details['revision'] ?? null))<p class="text-body">{{ $details['revision'] }}</p>@endif
    </details>
@endif

<h3>Requirement Checklist</h3>
<ul class="slu-review-checklist">
    @foreach($requirements as $key => $label)
        @php
            $path = $item->attachmentPath($key);
            $file = $files[$key] ?? null;
            $filename = $path ? (is_array($file) ? ($file['name'] ?? basename($path)) : basename($path)) : null;
            $isImage = $path && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true);
        @endphp
        <li>
            @include('workspace.slu-review-attachment')
            @if($isImage)
                @include('workspace.membership-image-preview')
            @endif
        </li>
    @endforeach
</ul>
@if($otherAttachments)
    <details>
        <summary>Additional Attachments</summary>
        @include('workspace.membership-attachments', ['attachments' => $otherAttachments])
    </details>
@endif

@if($reviewActions)
    <form class="slu-membership-decision" method="post" action="/staff/requests/{{ $item->id }}">
        @csrf
        @error('workflow')<p class="errors" role="alert">{{ $message }}</p>@enderror
        <label for="slu-membership-remarks">Reviewer Remarks</label>
        <textarea id="slu-membership-remarks" name="review_note" minlength="5" maxlength="3000"
                  placeholder="Add remarks (optional for approval)" aria-describedby="slu-membership-remarks-help">{{ old('review_note') }}</textarea>
        <p class="slu-membership-help" id="slu-membership-remarks-help">Remarks are required for correction, rejection, or starting review.</p>
        @error('review_note')<p class="errors" role="alert">{{ $message }}</p>@enderror
        <div class="slu-membership-decisions">
            @if(isset($reviewActions['needs_revision']))
                <button type="submit" class="sfu-button sfu-outline" name="status" value="needs_revision">Return for Correction</button>
            @endif
            @if(isset($reviewActions['rejected']))
                <button type="submit" class="sfu-button sfu-outline" name="status" value="rejected">Reject Application</button>
            @endif
            @if(isset($reviewActions['unit_approved']))
                <button type="submit" class="sfu-button slu-membership-approve" name="status" value="unit_approved">Approve &amp; Send to Assistant</button>
            @endif
        </div>
        <p class="slu-membership-help slu-membership-decision-note">The student joins the organization after final Admin approval.</p>
        @if(isset($reviewActions['in_review']))
            <button type="submit" class="slu-membership-start" name="status" value="in_review">Start Unit Review</button>
        @endif
    </form>
@endif
@if($item->review_note || !$reviewActions)
    <h3>{{ $item->status === 'rejected' ? 'Reason for Rejection' : 'Saved Reviewer Remarks' }}</h3>
    <p class="text-body">{{ $item->review_note ?? 'No remarks recorded.' }}</p>
@endif
