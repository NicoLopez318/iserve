<section class="panel" aria-labelledby="service-feedback-title">
    <h2 id="service-feedback-title">CVU Feedback</h2>
    @forelse($assignment->feedbackEntries as $entry)
        <article>
            <small class="muted">{{ $entry->author?->name ?? 'CVU Head' }} · {{ $entry->created_at->format('M j, Y g:i A') }}</small>
            <p class="pre-wrap">{{ $entry->message }}</p>
        </article>
    @empty
        <p class="muted">No feedback for this violation yet.</p>
    @endforelse
</section>
