@extends('layouts.portal')

@section('subtitle', 'Announcements and notifications from the Student Leadership Unit')

@section('content')
    @include('portal.activity-document-reminders')
    @forelse($announcements as $announcement)
        <article class="panel">
            <div class="section-heading">
                <h2>{{ $announcement->title }}</h2>
                <small>{{ $announcement->publishedAt->format('M j, Y') }}</small>
            </div>
            @if(!empty($announcement->metadata['image']))
                <img src="/content-image/{{ $announcement->id }}" alt="" style="max-width:100%;max-height:240px;object-fit:contain">
            @endif
            <p class="pre-wrap">{{ $announcement->body }}</p>
            @php($destination = \App\Support\PortalContent::destination($announcement->metadata['cta_destination'] ?? null))
            @if($destination && str_starts_with($destination, '/portal/leadership/'))
                <a href="{{ $destination }}" class="btn orange">{{ $announcement->metadata['cta_label'] ?? 'View details' }}</a>
            @endif
        </article>
    @empty
        <section class="panel empty">No Leadership Portal announcements yet.</section>
    @endforelse
    <a class="btn secondary" href="/portal/leadership">Back to Leadership Dashboard</a>
@endsection
