@extends('layouts.blue')
@section('content')<div class="center-page wide-form"><h1>Notifications</h1><section class="blue-card">@forelse($items as $item)
@php($data = is_array($item->data) ? $item->data : json_decode((string) $item->data, true))
<a class="notification-row" href="{{ $data['url']??'/home' }}"><strong>{{ $data['title']??'Update' }}</strong><p>{{ $data['message']??'' }}</p><small>{{ $item->created_at->diffForHumans() }}</small></a>@empty<p class="empty">You're all caught up.</p>@endforelse<a class="back-link" href="/home">Back to iServe</a></section></div>@endsection

