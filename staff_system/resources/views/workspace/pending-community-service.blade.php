<section class="panel" id="pending-service" aria-labelledby="pending-service-title">
    <h2 id="pending-service-title">Check Pending Community Service</h2>
    <form method="get" action="{{ url('/workspace/community') }}#pending-service" class="toolbar">
        <div class="filters">
            <label class="field">Student
                <select name="check_student" required>
                    <option value="">Select a student</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected($checkedStudent?->id === $student->id)>
                            {{ $student->student_id }} · {{ $student->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button>Check Status</button>
        </div>
    </form>
    @if($checkedStudent)
        @php($pending = $assignments->where('user_id', $checkedStudent->id)->where('status', 'active'))
        <h3>{{ $checkedStudent->name }}</h3>
        <p role="status">
            {{ $pending->isEmpty() ? 'No pending community service.' : $pending->count().' active '.Str::plural('assignment', $pending->count()).'.' }}
        </p>
        @foreach($pending as $record)
            @php($remaining = max(0, $record->required_minutes - $record->attendances->where('status','approved')->sum('minutes')))
            @php($waitingSessions = $record->attendances->where('status','pending')->count())
            <div class="summary-line">
                <div>
                    <strong>{{ $record->record_number }} · {{ $record->offense }}</strong>
                    <small>{{ number_format($remaining / 60, 2) }} hours remaining ·
                        {{ $waitingSessions }} {{ Str::plural('session', $waitingSessions) }} awaiting review ·
                        {{ $record->journals->where('status','approved')->count() }}/{{ count(config('portal.journals')) }} journal modules approved</small>
                </div>
                <button type="button" class="outline" data-dialog="assignment-{{ $record->id }}">Review</button>
            </div>
        @endforeach
    @endif
</section>
