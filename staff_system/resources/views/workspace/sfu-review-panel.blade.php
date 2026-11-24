@php
    $documents = [
        'application' => 'Application Form',
        'photo' => '1 x 1 ID Picture',
        'registration' => 'Registration Form',
        'certificate_of_employment' => 'Certificate Of Employment',
        'company_id' => 'Company ID',
        'moa' => 'MOA',
        'affidavit' => 'Affidavit',
    ];
@endphp

<section class="sfu-panel sfu-review" aria-labelledby="sfu-review-title">
    <h2 id="sfu-review-title">Application Review</h2>
    @php
        $reviewActions = $selectedRequest?->reviewActions(auth()->user()) ?? [];
        $reviewAvailable = isset($reviewActions['needs_revision'], $reviewActions['unit_approved']);
    @endphp
    @if($selectedRequest)
        @php
            $item = $selectedRequest;
            $files = $item->files ?? [];
            $checklist = $documents + collect($files)->mapWithKeys(fn($file, $key) => [$key => str($key)->headline()->toString()])->all();
        @endphp
        <div class="sfu-review-identity">
            <a class="sfu-ticket" href="/workspace/request/{{ $item->id }}" title="Open application details and all review actions">{{ $item->ticket }}</a>
            <p>{{ $item->user->name }}</p>
            @if($program = $item->details['student_type'] ?? $item->user->course)<p class="sfu-review-program">{{ $program === 'Graduate' ? 'Graduate School' : $program }}</p>@endif
            @php
                $applicationTerm = implode(' · ', array_filter([$item->details['academic_year'] ?? null, $item->details['semester'] ?? null]));
            @endphp
            @if($applicationTerm)<p class="sfu-review-term">AY {{ $applicationTerm }}</p>@endif
        </div>

        <h3>Requirement Checklist</h3>
        <ul class="sfu-checklist">
            @foreach($checklist as $key => $label)
                @php
                    $file = $files[$key] ?? null;
                @endphp
                <li>
                    <img class="sfu-check" src="/staff-assets/{{ $file ? '21-4770.svg' : '21-4776.svg' }}" alt="{{ $file ? 'Submitted' : 'Not submitted' }}">
                    <span>{{ $label }}</span>
                    @if($file)
                        @php
                            $fileSize = is_array($file) ? ($file['size'] ?? null) : null;
                        @endphp
                        <a class="sfu-attachment" href="{{ route('staff.requests.attachment-view', ['serviceRequest' => $item, 'key' => $key]) }}" target="_blank" rel="noopener" aria-label="Open {{ $label }}">
                            <img src="/staff-assets/21-4782.svg" alt="">{{ $fileSize ? '('.number_format($fileSize / 1048576, 1).' MB)' : 'View' }}
                        </a>
                    @else
                        <span class="sfu-attachment sfu-attachment-missing" aria-hidden="true"><img src="/staff-assets/21-4782.svg" alt="">—</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if($reviewAvailable)
            <form id="sfu-review-form" class="sfu-review-form" method="post" action="/staff/requests/{{ $item->id }}">
                @csrf
                <label for="sfu-remarks">Reviewer Remarks</label>
                <textarea id="sfu-remarks" name="review_note" minlength="5" maxlength="3000" placeholder="Add Remarks (Optional)">{{ old('review_note') }}</textarea>
                @if(old('_dialog') !== 'sfu-validate')
                    @error('review_note')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
                @endif
            </form>
            @push('dialogs')
                <dialog id="sfu-validate" aria-labelledby="sfu-validate-title">
                    <button class="close-dialog" data-close aria-label="Close">×</button>
                    <form class="panel" method="post" action="/staff/requests/{{ $item->id }}">
                        @csrf
                        <h2 id="sfu-validate-title">Validate Application</h2>
                        <label class="field">Discount Rate (%)<input name="discount_rate" type="number" min="1" max="100" required value="{{ old('discount_rate', $item->details['discount_rate'] ?? '') }}"></label>
                        @error('discount_rate')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
                        <label class="field">Reviewer Remarks (Optional)<textarea name="review_note" minlength="5" maxlength="3000">{{ old('review_note') }}</textarea></label>
                        @error('review_note')<p class="sfu-review-error" role="alert">{{ $message }}</p>@enderror
                        <div class="actions"><button name="status" value="unit_approved">Validate Application</button></div>
                    </form>
                </dialog>
            @endpush
        @else
            <h3>Reviewer Remarks</h3>
            <p class="sfu-readonly-remarks">{{ $item->review_note ?: 'No reviewer remarks yet.' }}</p>
            <p class="sfu-review-state">{{ match($item->status) {
                'approved' => 'This application has already been approved.',
                'needs_revision' => 'Waiting for the applicant to resubmit.',
                'unit_approved', 'awaiting_admin' => 'This application has passed SFU review.',
                default => 'This application is outside the SFU review stage.',
            } }}</p>
        @endif
    @else
        <p class="sfu-empty">Select an application to review.</p>
    @endif
    <div class="sfu-review-actions">
        <button class="sfu-button sfu-outline" type="submit" name="status" value="needs_revision" @if($reviewAvailable) form="sfu-review-form" @else disabled @endif>Return for Compliance</button>
        <button class="sfu-button" type="button" @if($reviewAvailable) data-dialog="sfu-validate" @else disabled @endif>Validate Application</button>
    </div>
    <p class="sfu-review-hint">Complete applications are normally processed within one day.</p>
</section>
