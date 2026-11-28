@if($assignment)
    <section class="panel">
        @if($assignments->count() > 1)
            <form method="get">
                <label>Violation
                    <select name="assignment">
                        @foreach($assignments as $record)
                            <option value="{{ $record->id }}" @selected($assignment->id === $record->id)>
                                {{ $record->record_number }} · {{ $record->offense }} ({{ ucfirst($record->status) }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <button class="btn primary">View Record</button>
            </form>
        @else
            <p><strong>{{ $assignment->record_number }}</strong> · {{ $assignment->offense }}</p>
        @endif
    </section>
@endif
