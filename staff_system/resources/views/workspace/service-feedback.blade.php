<section aria-labelledby="feedback-title-{{ $assignment->id }}">
    <h3 id="feedback-title-{{ $assignment->id }}">Feedback to Student</h3>
    @if(session('community_feedback_assignment') === $assignment->id)
        <p role="status">{{ session('success') }}</p>
    @endif
    <form method="post" action="{{ route('staff.community.feedback.store', $assignment) }}">
        @csrf
        @php($draft = old('_dialog') === 'assignment-'.$assignment->id ? old('feedback') : null)
        <label class="field">Message
            <textarea name="feedback" rows="3" maxlength="3000" required>{{ is_string($draft) ? $draft : '' }}</textarea>
        </label>
        <button type="submit">Send Feedback</button>
    </form>
    @forelse($assignment->feedbackEntries as $entry)
        <article>
            <small class="muted">{{ $entry->author?->name ?? 'CVU Head' }} · {{ $entry->created_at->format('M j, Y g:i A') }}</small>
            <p class="text-body">{{ $entry->message }}</p>
        </article>
    @empty
        <p class="muted">No feedback sent yet.</p>
    @endforelse
</section>
