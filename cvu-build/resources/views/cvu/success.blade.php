@extends('cvu.layout')

@section('content')
<section class="flow-card result">
    @if($q->kind === 'readmission')
        <h1>Re-Admission Request</h1>
        <p>Reference number <strong class="ticket">{{ $q->ticket }}</strong></p>
        <p>Status <strong>{{ str($q->status)->headline() }}</strong></p>
        <p>{{ match($q->status) {
            'approved' => 'Approved for Admission. Present your virtual slip to your instructor.',
            'rejected' => 'Your request was declined.',
            'cancelled' => 'This request was cancelled.',
            'needs_revision' => 'The CVU needs more information before it can continue reviewing your request.',
            'unit_approved', 'awaiting_admin' => 'Your request is awaiting final approval.',
            default => 'Your request is with the CVU for review.',
        } }}</p>
        @if(filled($q->details['interview_at'] ?? null))
            <p>Interview <strong>{{ str_replace('T', ' ', $q->details['interview_at']) }}</strong></p>
        @endif
        @if($q->status === 'approved')
            <h2>Re-Admission Virtual Slip</h2>
            <a class="orange" href="{{ route('readmission.slip', $q) }}">View and Print Slip</a>
        @endif
        <p><a href="/requests/{{ $q->id }}">{{ $q->status === 'needs_revision' ? 'Provide requested information' : 'View request details' }}</a></p>
    @else
        <h1>{{ $q->kind === 'clearance' ? 'Appointment Submitted' : ($q->kind === 'seminar' ? 'Seminar Registration Submitted' : 'Documents Submitted Successfully') }}</h1>
        @if($q->kind === 'seminar')
            <p>Your seminar registration has been recorded. Attendance is subject to facilitator verification.</p>
        @else
            <p>Your request has been received and is ready for review.</p>
        @endif
        <p>You will be notified once your application has been evaluated.</p>
        @if($q->kind === 'clearance')
            <h3>Ticket Number</h3><strong class="ticket">{{ $q->ticket }}</strong>
            <p>Present this number at the office for validation.</p>
            <h3>Appointment Details</h3>
            <p>Date: {{ $q->details['appointment_date'] ?? '—' }}<br>
                Time: {{ $q->details['schedule'] ?? '—' }}<br>
                Status: {{ $q->status === 'approved' ? 'completed' : (in_array($q->status, ['unit_approved', 'awaiting_admin']) ? 'confirmed' : ($q->details['appointment_status'] ?? $q->status)) }}</p>
        @endif
        @if($q->status === 'needs_revision')
            <a href="/requests/{{ $q->id }}">Provide requested information</a>
        @endif
    @endif
    @if($q->review_note)<p>{{ $q->review_note }}</p>@endif
    @if($q->kind !== 'readmission')<div class="success-symbol">✓</div>@endif
    <a class="orange" href="/transactions">Done</a>
</section>
@endsection
