<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Re-Admission Virtual Slip · {{ $item->ticket }}</title>
    <link rel="stylesheet" href="{{ asset('cvu-workspace.css') }}?v=9">
    <script src="{{ asset('staff.js') }}" defer></script>
</head>
<body class="readmission-slip-page">
    @php
        $details = $item->details ?? [];
        $interview = strtotime($details['interview_at'] ?? '');
        $issued = strtotime($details['issued_at'] ?? $details['slip_issued_at'] ?? '');
    @endphp
    <main class="readmission-card">
        <h1>Re-Admission Virtual Slip</h1>
        @if(session('success'))<p class="readmission-card-notice" role="status">{{ session('success') }}</p>@endif
        @include('workspace.readmission-card-identity', ['referenceLabel'=>'Slip Number'])
        <dl class="readmission-card-section">
            <div><dt>Decision</dt><dd class="readmission-card-approved">Approved for Admission</dd></div>
            <div><dt>Interview Date</dt><dd>{{ $interview !== false ? date('M j, Y', $interview) : '—' }}</dd></div>
            <div><dt>Remarks</dt><dd>{{ $item->review_note ?: 'Student may return to class and present this virtual slip to the instructor.' }}</dd></div>
        </dl>
        <dl class="readmission-card-grid readmission-card-issued">
            <div><dt>Issued By</dt><dd>{{ $details['issued_by'] ?? 'CSLD' }}</dd></div>
            <div><dt>Date Issued</dt><dd>{{ $issued !== false ? date('M j, Y', $issued) : ($item->reviewed_at?->format('M j, Y') ?? '—') }}</dd></div>
        </dl>
        <p class="readmission-card-note">This is a system-generated re-admission slip. Present it to your instructor when you return to class.</p>
        <div class="readmission-card-actions">
            @if(auth()->user()->role==='cvu_head')
                <form method="post" action="{{ route('staff.readmission-slip.send', $item) }}">
                    @csrf
                    <button type="submit" class="readmission-card-primary">Send to Student</button>
                </form>
            @endif
            <button type="button" data-print class="readmission-card-secondary">Print / Save PDF</button>
        </div>
    </main>
</body>
</html>
