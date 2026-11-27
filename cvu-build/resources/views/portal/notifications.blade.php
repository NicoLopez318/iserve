@extends('layouts.blue')
@section('content')
<div class="center-page wide-form">
    <h1>Notifications</h1>
    <section class="blue-card">
        @forelse($items as $item)
            @php
                $data = $item->data;
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }
                $url = $data['url'] ?? '/home';
                if (!auth()->user()->isStaff() && is_string($url)) {
                    $parts = parse_url(str_starts_with($url, '/') || str_contains($url, '://') ? $url : '//'.$url);
                    $path = $parts['path'] ?? '';
                    if (preg_match('~\A/(?:requests/\d+|(?:cvu|leadership|scholar)/submitted/\d+|apply/seminar|portal/(?:values|leadership|scholar)(?:/[a-z-]+)?)\z~', $path)) {
                        $url = $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
                    }
                }
            @endphp
            <a class="notification-row" href="{{ $url }}">
                <strong>{{ $data['title'] ?? 'Update' }}</strong>
                <p>{{ $data['message'] ?? '' }}</p>
                <small>{{ $item->created_at->diffForHumans() }} · View details →</small>
            </a>
        @empty
            <p class="empty">You're all caught up.</p>
        @endforelse
    </section>
    <a class="btn secondary notification-back" href="/home"><span aria-hidden="true">←</span> Back to iServe</a>
</div>
@endsection

