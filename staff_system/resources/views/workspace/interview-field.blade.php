<label class="field">Interview Date &amp; Time
    <input type="datetime-local" name="interview_at" value="{{ old('interview_at') }}">
</label>
@if($item->kind==='readmission')
    <p class="muted">Leave this blank to save remarks and keep the current interview date.</p>
@endif
@error('interview_at')<p class="errors" role="alert">{{ $message }}</p>@enderror
