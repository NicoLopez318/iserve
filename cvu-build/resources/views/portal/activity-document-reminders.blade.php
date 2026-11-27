@if($activityReminders)
    <section class="panel" aria-labelledby="activity-document-reminders">
        <h2 id="activity-document-reminders">Activity Document Reminders</h2>
        @foreach($activityReminders as $reminder)
            <article class="record-row">
                <div>
                    <strong>{{ $reminder['stage'] }} · {{ $reminder['title'] }}</strong>
                    @if($reminder['documents'])
                        <p>Pending off-campus checklist items: {{ implode(', ', $reminder['documents']) }}.</p>
                    @endif
                </div>
                <a class="btn secondary" href="{{ $reminder['url'] }}">{{ $reminder['action'] }}</a>
            </article>
        @endforeach
    </section>
@endif
