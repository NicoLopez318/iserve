@php($isSfuWorkspace = in_array($page ?? '', ['dashboard', 'applications', 'reports', 'settings']) && auth()->user()->role === 'sfu_head')
@php($usesUnitLayout = $isSfuWorkspace || (in_array($page ?? '', ['dashboard', 'organizations', 'proposals', 'events', 'achievements', 'points', 'reports', 'content', 'settings']) && auth()->user()->role === 'slu_head'))
@php($usesCvuLayout = in_array($page ?? '', ['dashboard', 'complaints', 'lost-found'], true) && auth()->user()->role === 'cvu_head')
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $design['title'] ?? $title ?? 'iServe CSLD' }}</title><link rel="stylesheet" href="/staff.css?v=20261122.3"><script src="/staff.js?v=20261127.1" defer></script><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112">@if($usesUnitLayout)<link rel="stylesheet" href="/sfu-dashboard.css?v=32"><script src="/sfu-dashboard.js?v=10" defer></script>@endif @if($usesCvuLayout)<link rel="stylesheet" href="/cvu-workspace.css?v=6">@endif</head>
<body data-page="{{$page??'review'}}" data-role="{{auth()->user()->role}}" class="{{ auth()->user()->preferences['theme']??'Light' }}{{ $usesUnitLayout ? ' unit-workspace' : '' }}{{ $usesCvuLayout ? ' cvu-workspace' : '' }}"><div class="workspace">
<aside class="sidebar"><a class="brand" href="/workspace"><img src="/staff-assets/afaaeb1608414bb233036355bd45d485ad6921e0.png" alt="University crest"><span>iServe</span></a><nav aria-label="Staff navigation">
@foreach($menus as $key=>$label)
 @if(in_array(auth()->user()->role,['admin','assistant'])&&$key==='leadership')<div class="nav-caption">CSLD UNITS</div>@endif
 <a href="/workspace/{{ $key }}" class="nav-item {{ ($page??'')===$key?'active':'' }}"><span class="nav-icon{{ $key === 'dashboard' ? ' dashboard-nav-icon' : '' }}">@include('workspace.icon',['name'=>$key])</span><span>{{ $label }}</span>@if(in_array($key,['leadership','scholarship','values']))<span class="chevron">›</span>@endif</a>
@endforeach
</nav><form action="/logout" method="post" class="logout">@csrf<button>@include('workspace.icon',['name'=>'logout']) Log out</button></form></aside>
<main>
<header>
    <div><h1>{{ $design['title']??$title??'Review' }}</h1><p>{{ $design['description']??$description??'' }}</p></div>
    @php($staffUser = auth()->user())
    @if($staffUser->isStaff())
        @php($unreadCount = $staffUser->unreadNotifications()->count())
        <div class="header-account">
            <a class="notification-link" href="/notifications" aria-label="Notifications ({{ $unreadCount }} unread)" @if(($page ?? '') === 'notifications') aria-current="page" @endif>
                @if($usesCvuLayout)<span class="cvu-header-icon" aria-hidden="true">🔔</span>@else<span class="notification-bell" aria-hidden="true"></span>@endif
                <span aria-hidden="true">{{ $unreadCount }}</span>
            </a>
            @if($usesCvuLayout)
                <time class="cvu-date" datetime="{{ today()->toDateString() }}"><span class="cvu-header-icon" aria-hidden="true">🗓️</span>{{ today()->format('M j, Y') }}</time>
            @elseif($usesUnitLayout)
                <time class="sfu-date" datetime="{{ today()->toDateString() }}"><span aria-hidden="true">🗓️</span>{{ today()->format('M j, Y') }}</time>
            @elseif($staffUser->role !== 'admin')
                <span>{{ now()->format('F j, Y') }}</span>
                <a class="avatar" href="/workspace/settings" title="{{ $staffUser->name }}">{{ substr($staffUser->name, 0, 1) }}</a>
            @endif
        </div>
    @endif
</header>
@if(session('success'))<div class="notice" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
@yield('content')
</main></div>
@stack('dialogs')
@if($errors->any()&&old('_dialog'))<script type="application/json" id="form-restore">{!! json_encode(['dialog'=>old('_dialog'),'fields'=>collect(session()->getOldInput())->except(['_token','password','password_confirmation','current_password'])->all()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endif
</body></html>
