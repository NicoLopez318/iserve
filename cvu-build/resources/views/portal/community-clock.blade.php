@php
    $sessions = $attendances->where('assignment_id', $assignment->id);
    $todaySession = $sessions->first(fn ($entry) => $entry->time_in?->isToday());
    $openSession = $sessions->first(fn ($entry) => $entry->time_in && !$entry->time_out);
    $displaySession = $openSession ?? $todaySession;
@endphp
<div class="two-columns">
    @foreach (['in' => 'Time In', 'out' => 'Time Out'] as $action => $label)
        @php
            $reason = match (true) {
                $assignment->status !== 'active' => 'This community service assignment is closed.',
                $action === 'in' && $openSession !== null => 'Time In is already recorded. Use Time Out to close the session.',
                $action === 'in' && $todaySession !== null => 'You have already completed today’s session.',
                $action === 'out' && $openSession === null => $todaySession
                    ? 'Time Out is already recorded for today.'
                    : 'Record Time In first.',
                default => null,
            };
        @endphp
        <section class="panel clock-card">
            <p>{{ ($displaySession?->time_in ?? now())->format('F j, Y') }}</p>
            <h2>{{ $displaySession?->{'time_'.$action}?->format('g:i A') ?? '— : —' }}</h2>
            @if ($reason)
                <button type="button" class="btn {{ $action === 'in' ? 'green' : 'red' }}" disabled>{{ $label }}</button>
                <p class="muted">{{ $reason }}</p>
            @else
                <form action="/community/clock" method="post" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="action" value="{{ $action }}">
                    <label class="camera-input">
                        <img src="{{ asset('design-assets/17121.svg') }}" alt="">
                        <span class="sr-only">Take or choose an evidence photo</span>
                        <input type="file" name="photo" accept="image/*" capture="user" required>
                    </label>
                    <button type="submit" class="btn {{ $action === 'in' ? 'green' : 'red' }}">{{ $label }}</button>
                </form>
            @endif
        </section>
    @endforeach
</div>
