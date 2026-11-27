@extends('layouts.blue')
@section('title','Documents Submitted Successfully · iServe')
@section('bodyClass','leadership-onboarding')
@section('content')
<div class="center-page leadership-success"><section class="blue-card">
<h1>Documents Submitted Successfully</h1>
<p>Your uploaded documents have been received and are now ready for review.</p>
<div class="leadership-notice" role="status" aria-live="polite">
    <span class="leadership-notice-icon" aria-hidden="true">🔔</span>
    <div>
        <strong>{{ match ($application->status) {
            'in_review' => 'Under review by the SLU Head',
            'unit_approved' => 'Awaiting Assistant forwarding',
            'awaiting_admin' => 'Awaiting final Admin approval',
            default => 'Awaiting SLU Head review',
        } }}</strong>
        <p>Application <strong>{{ $application->ticket }}</strong> is still in progress. You can propose an activity after final Admin approval.</p>
        <p>You will receive an in-app notification when your application status changes.</p>
    </div>
</div>
<a href="/" class="btn orange full">Done</a>
</section></div>
@endsection
