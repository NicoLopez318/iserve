@php($time = $record->{'time_'.$side})
<td>
    @if($time)
        <time datetime="{{ $time->toAtomString() }}">{{ $time->format('M j, Y g:i A') }}</time>
    @else
        —
    @endif
    @if($record->{'photo_'.$side})
        @php($preview = url('/staff/attendance/'.$record->id.'/evidence/'.$side).'?preview=1')
        <a class="attendance-photo" href="{{ $preview }}" target="_blank" rel="noopener">
            <img src="{{ $preview }}" alt="Time {{ $side }} photo" width="80" height="64" loading="lazy">
        </a>
    @endif
</td>
