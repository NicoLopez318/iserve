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
        <strong>Student Leadership Unit Head notified</strong>
        <p>Application <strong>{{ $application->ticket }}</strong> is waiting for review. You will receive an in-app notification when its status is updated.</p>
    </div>
</div>
<a href="/" class="btn orange full">Done</a>
</section></div>
@endsection