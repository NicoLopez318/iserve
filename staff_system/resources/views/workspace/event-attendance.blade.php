<h2 id="event-{{ $e->id }}-attendance-title">{{ $e->title }} · Participants &amp; Attendance</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>Student</th><th>Student ID</th><th>Course / Year</th><th>Time In</th><th>Time Out</th><th>Attendance</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($records as $a)
                <tr>
                    <td>{{ $a->user->name }}</td><td>{{ $a->user->student_id }}</td><td>{{ $a->user->course }} / {{ $a->user->year_level }}</td>
                    @include('workspace.attendance-time', ['record' => $a, 'side' => 'in'])
                    @include('workspace.attendance-time', ['record' => $a, 'side' => 'out'])
                    <td>{{ $a->status }}</td>
                    <td>
                        @if($a->status === 'pending')
                            <form method="post" action="/staff/attendance/{{ $a->id }}">
                                @csrf
                                <input name="review_note" required minlength="5" placeholder="Review note" aria-label="Review note">
                                <button class="table-action" name="status" value="approved">Verify</button>
                                <button class="table-action outline" name="status" value="rejected">Return</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No participants.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
