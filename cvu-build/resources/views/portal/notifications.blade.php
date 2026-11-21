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
            @endphp
            <a class="notification-row" href="{{ $data['url'] ?? '/home' }}">
                <strong>{{ $data['title'] ?? 'Update' }}</strong>
                <p>{{ $data['message'] ?? '' }}</p>
                <small>{{ $item->created_at->diffForHumans() }}</small>
            </a>
        @empty
            <p class="empty">You're all caught up.</p>
        @endforelse
        <a class="back-link" href="/home">Back to iServe</a>
    </section>
</div>
@endsection

