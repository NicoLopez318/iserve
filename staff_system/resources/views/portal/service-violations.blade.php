<section class="panel">
    <h2>Recorded Violations</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Record</th><th>Violation</th><th>Severity</th><th>Verified / Required Hours</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($assignments as $record)
                    <tr>
                        <td>{{ $record->record_number }}</td>
                        <td>{{ $record->offense }}</td>
                        <td>{{ $record->severity }}</td>
                        <td>{{ number_format($record->verified_minutes/60, 2) }} / {{ $record->required_minutes/60 }}</td>
                        <td><span class="badge {{ $record->status }}">{{ ucfirst($record->status) }}</span></td>
                        <td><a href="{{ url('/portal/values/community-service').'?assignment='.$record->id }}">View Service Record</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No recorded violations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
