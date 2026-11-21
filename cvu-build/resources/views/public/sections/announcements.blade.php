<div class="section-intro">
    <p class="section-label">CSLD updates</p>
    <h2 id="announcements-title">Announcements</h2>
</div>
<form class="announcement-search" action="/#announcements" role="search" aria-label="Search announcements">
    <input name="announcement_q" type="search" aria-label="Search announcements" maxlength="200"
           value="{{ $announcementSearch }}" placeholder="Search announcements">
    <select name="announcement_unit" aria-label="Announcement unit">
        <option value="">All units</option>
        @foreach ($units as $key => $unit)
            <option value="{{ $key }}" @selected($announcementUnit === $key)>{{ $unit['short'] }}</option>
        @endforeach
    </select>
    <button class="portal-button">Search</button>
</form>

@forelse ($announcements as $notice)
    @php($metadata = \App\Support\PortalContent::metadata($notice->id))
    <article class="announcement-card">
        <small>{{ $units[$notice->unit]['name'] }} · {{ \Carbon\Carbon::parse($notice->published_at)->format('F j, Y') }}</small>
        <h3>{{ $notice->title }}</h3>
        @if (isset($metadata['image']))
            <img src="/content-image/{{ $notice->id }}" alt="{{ $notice->title }}" loading="lazy">
        @endif
        <p>{{ $notice->body }}</p>
        @if ($noticeDestination = \App\Support\PortalContent::destination($metadata['cta_destination'] ?? null))
            <a href="{{ $noticeDestination }}">{{ $metadata['cta_label'] ?? 'View' }}</a>
        @endif
    </article>
@empty
    <p>{{ $announcementSearch || $announcementUnit ? 'No matching announcements.' : 'No announcements published yet.' }}</p>
@endforelse
