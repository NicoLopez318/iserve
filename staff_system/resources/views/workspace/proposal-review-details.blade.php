@php
    $details = $item->details ?? [];
    $files = $item->files ?? [];
    $completionReport = $details['completion_report'] ?? false;
    $heading = $completionReport ? 'Completion Report' : 'Proposal';
    $documentLabels = config('portal.documents.activity', []);
    $documentGroups = $completionReport ? [] : ['Pre-Activity' => [], 'Post-Activity' => []];
    foreach ($files as $key => $file) {
        $group = match (true) {
            !$completionReport && str_starts_with($key, 'pre_') => 'Pre-Activity',
            !$completionReport && str_starts_with($key, 'post_') => 'Post-Activity',
            default => 'Supporting Files',
        };
        $documentGroups[$group][$key] = $documentLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }
@endphp

<h2>{{ $heading }} {{ $reviewActions ? 'Review' : 'Details' }}</h2>
<div class="proposal-review-header">
    <div>
        <strong>{{ $details['title'] ?? $item->ticket }}</strong>
        <p class="muted">{{ $item->ticket }} · Submitted by {{ $item->user->name }}</p>
    </div>
    <span class="status {{ $item->status }}">{{ ucwords(str_replace('_', ' ', $item->status)) }}</span>
</div>

<dl class="compact-details proposal-review-facts">
    @if($organization = $item->user->organization?->name)
        <div><dt>Organization</dt><dd>{{ $organization }}</dd></div>
    @endif
    @foreach(['starts_at' => 'Starts', 'ends_at' => 'Ends'] as $key => $label)
        @if(!empty($details[$key]))
            <div><dt>{{ $label }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($details[$key])->format('M j, Y g:i A') }}</dd></div>
        @endif
    @endforeach
    @foreach(['venue' => 'Venue', 'budget' => 'Budget', 'capacity' => 'Expected Participants', 'points' => 'Participation Points', 'event_id' => 'Event Reference'] as $key => $label)
        @if(isset($details[$key]))
            <div><dt>{{ $label }}</dt><dd>{{ $details[$key] }}</dd></div>
        @endif
    @endforeach
</dl>

@if(!empty($details['description']))
    <h3>{{ $completionReport ? 'Summary' : 'Activity Description' }}</h3>
    <p class="text-body">{{ $details['description'] }}</p>
@endif
@if(!empty($details['revision']))
    <h3>Applicant’s Revision</h3>
    <p class="text-body">{{ $details['revision'] }}</p>
@endif

<h3>{{ $completionReport ? 'Attachments' : 'Requirement Documents' }}</h3>
@forelse($documentGroups as $group => $documents)
    <section class="proposal-document-group">
        @unless($completionReport)<h4>{{ $group }}</h4>@endunless
        @forelse($documents as $key => $label)
            @php($filename = is_array($files[$key]) ? ($files[$key]['name'] ?? $label) : basename($files[$key]))
            <a class="file" href="{{ url('/requests/'.$item->id.'/files/'.rawurlencode($key)) }}">
                <span><strong>{{ $label }}</strong><small>{{ $filename }}</small></span><small>Download</small>
            </a>
        @empty
            <p class="muted">No files attached.</p>
        @endforelse
    </section>
@empty
    <p class="muted">No supporting files attached.</p>
@endforelse
