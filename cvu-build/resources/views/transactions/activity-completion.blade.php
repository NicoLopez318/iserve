@extends('layouts.blue')
@section('title','After-Event Report · iServe')
@section('content')
<div class="center-page wide-form">
    <h1>After-Event Report</h1>
    <section class="blue-card">
        @include('components.flash')
        <h2>{{ $event?->title ?? $proposal->details['title'] }}</h2>
        <p>Proposal {{ $proposal->ticket }}</p>
        @if($report)
            <p>Report status <strong>{{ str($report->status)->headline() }}</strong></p>
            <a class="btn orange" href="/requests/{{ $report->id }}">{{ $report->status === 'needs_revision' ? 'Revise report' : 'View submitted report' }}</a>
        @elseif($canSubmit)
            <form method="post" action="{{ route('activity.completion.store', $proposal) }}" enctype="multipart/form-data">
                @csrf
                <label>Report summary<textarea name="summary" rows="5" required minlength="30" maxlength="5000">{{ old('summary') }}</textarea></label>
                <label>After-event report file <small>(PDF, DOCX, PNG, or JPG, up to 10 MB)</small>
                    <input type="file" name="report" accept=".pdf,.docx,.png,.jpg,.jpeg" data-max-mb="10" required>
                </label>
                <label class="check"><input type="checkbox" name="declaration" value="1" @checked(old('declaration')) required>I confirm that the report is accurate.</label>
                <button class="btn orange full">Submit after-event report</button>
            </form>
        @elseif(!$event)
            <p>This proposal has no linked event. Contact the Student Leadership Unit to check the event record.</p>
        @elseif($event->status === 'published' && $event->ends_at->isFuture())
            <p>You can submit your report after the event ends on {{ $event->ends_at->format('M j, Y, g:i A') }}.</p>
        @else
            <p>This event is closed to after-event reports.</p>
        @endif
        <a class="back-link" href="/portal/leadership/propose">Back to proposals</a>
    </section>
</div>
@endsection
