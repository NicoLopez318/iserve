@extends('layouts.blue')
@section('content')<div class="center-page wide-form"><h1>Notifications</h1><section class="blue-card">@forelse($items as $item)<a class="notification-row" href="{{ $item->data['url']??'/home' }}"><strong>{{ $item->data['title']??'Update' }}</strong><p>{{ $item->data['message']??'' }}</p><small>{{ $item->created_at->diffForHumans() }}</small></a>@empty<p class="empty">You're all caught up.</p>@endforelse<a class="back-link" href="/home">Back to iServe</a></section></div>@endsection

