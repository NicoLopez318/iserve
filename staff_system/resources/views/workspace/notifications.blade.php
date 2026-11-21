@extends('workspace.layout')

@section('content')
    <section class="panel notification-inbox" aria-label="Notification history">
        @if($items->isEmpty())
            <div class="notification-empty">
                <span class="notification-bell" aria-hidden="true"></span>
                <h2>No notifications yet</h2>
            </div>
        @else
            <ul class="notification-list">
                @foreach($items as $item)
                    @php
                        $data = is_array($item->data) ? $item->data : json_decode((string) $item->data, true);
                        $itemTitle = $data['title'] ?? 'Update';
                        $message = $data['message'] ?? '';
                        $destination = $data['url'] ?? null;
                    @endphp
                    <li @class(['notification-entry', 'notification-entry-new' => $item->unread()])>
                        @if($destination)
                            <a class="notification-entry-content" href="{{ $destination }}">
                        @else
                            <div class="notification-entry-content">
                        @endif
                            <span class="notification-entry-icon" aria-hidden="true"><span class="notification-bell"></span></span>
                            <div class="notification-entry-text">
                                <h2>{{ $itemTitle }}</h2>
                                @if($message !== '' && $message !== $itemTitle)
                                    <p>{{ $message }}</p>
                                @endif
                                <time datetime="{{ $item->created_at->toIso8601String() }}" title="{{ $item->created_at->format('M j, Y, g:i A') }}">
                                    {{ $item->created_at->diffForHumans() }}
                                </time>
                            </div>
                            @if($item->unread())
                                <span class="notification-new-badge">New</span>
                            @endif
                            @if($destination)
                                <span class="notification-entry-arrow" aria-hidden="true">›</span>
                            @endif
                        @if($destination)
                            </a>
                        @else
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
