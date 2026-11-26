<details class="review-history">
    <summary>Submission Timeline</summary>
    <div class="timeline">
        <article>
            <strong>Submitted</strong>
            @if($showSubmitter ?? false)
                <p>{{ $item->user->name }}@if($item->user->course) · {{ $item->user->course }}@endif</p>
            @endif
            <small>{{ $item->created_at->format('M j, Y g:i A') }}</small>
        </article>
        @foreach($history as $entry)
            <article>
                <strong>{{ ucwords(str_replace(['request.', '_'], ['', ' '], $entry->action)) }}</strong>
                <p>{{ $entry->details }}</p>
                <small>{{ $entry->name }} · {{ $entry->created_at }}</small>
            </article>
        @endforeach
    </div>
</details>
