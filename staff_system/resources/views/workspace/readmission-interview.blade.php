@php
    $savedTime = strtotime($item->details['interview_at'] ?? '');
    $remarks = old('review_note', $item->review_note);
@endphp
<section class="readmission-card">
    <h2 id="readmission-interview-title" tabindex="-1" autofocus>Schedule Interview</h2>
    @if(session('success'))<p class="readmission-card-notice" role="status">{{ session('success') }}</p>@endif
    @include('workspace.readmission-card-identity', ['referenceLabel'=>'Reference Number'])

    <form method="post" action="{{ url('/workspace/request/'.$item->id.'/note') }}">
        @csrf
        <input type="hidden" name="readmission_action" value="schedule">
        <div class="readmission-card-section">
            <label for="readmission-interview-at">Interview Date &amp; Time</label>
            <input type="datetime-local" id="readmission-interview-at" name="interview_at"
                value="{{ old('interview_at', $savedTime !== false ? date('Y-m-d\TH:i', $savedTime) : '') }}" required>
            @error('interview_at')<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
            <label for="readmission-interview-remarks">Remarks for the Student</label>
            <textarea id="readmission-interview-remarks" name="review_note" rows="3" minlength="5" maxlength="3000"
                required>{{ is_string($remarks) ? $remarks : '' }}</textarea>
            @error('review_note')<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
            @error('workflow')<p class="cvu-case-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="readmission-card-actions">
            <button type="submit" class="readmission-card-primary">Save Interview</button>
            <a href="{{ request()->fullUrlWithoutQuery(['panel']) }}">Back to Review</a>
        </div>
    </form>
</section>
