@php
    $serverTime = now();
    $deadline = $a->time_in ? $event->ends_at->copy()->addHour() : $event->ends_at;
    $label = $a->time_in ? 'Time Out' : 'Time In';
    $beforeWindow = $serverTime->lt($event->starts_at);
    $afterWindow = $serverTime->gt($deadline);
@endphp
<div class="button-row">
    <span class="badge {{ $a->status }}">{{ str_replace('_', ' ', $a->status) }}</span>
    @if ($a->time_in)
        <span>Time In <time datetime="{{ $a->time_in->toAtomString() }}">{{ $a->time_in->format('M j, g:i A') }}</time></span>
    @endif
    @if ($a->time_out)
        <span>Time Out <time datetime="{{ $a->time_out->toAtomString() }}">{{ $a->time_out->format('M j, g:i A') }}</time></span>
    @else
        <form method="post" action="/events/{{ $event->id }}" data-attendance-window
              data-server-time="{{ $serverTime->getTimestamp() }}" data-start="{{ $event->starts_at->getTimestamp() }}"
              data-end="{{ $deadline->getTimestamp() }}">
            @csrf
            <input type="hidden" name="action" value="{{ $a->time_in ? 'out' : 'in' }}">
            <button type="submit" class="btn {{ $a->time_in ? 'red' : 'green' }}" @disabled($beforeWindow || $afterWindow)>{{ $label }}</button>
            <small class="muted" data-before-window @unless($beforeWindow) hidden @endunless>{{ $label }} opens {{ $event->starts_at->format('M j, g:i A') }}.</small>
            <small class="muted" data-after-window @unless($afterWindow) hidden @endunless>{{ $label }} closed {{ $deadline->format('M j, g:i A') }}.</small>
        </form>
    @endif
</div>
