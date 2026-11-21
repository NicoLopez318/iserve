@php
$portal=$portal??'values';$page=$page??'dashboard';
$labels=['values'=>'Student Values Portal','leadership'=>'Student Leadership Portal','scholar'=>'Student Scholar Portal'];
$navs=['values'=>['dashboard'=>['Dashboard','0596a.svg'],'violations'=>['Violations','7ffe5.svg'],'community-service'=>['Community Service','19391.svg'],'journal'=>['Duty Journal','c1105.svg'],'profile'=>['Profile','25646.svg'],'settings'=>['Settings','bc21d.svg']],
'leadership'=>['dashboard'=>['Dashboard','0596a.svg'],'organization'=>['Organization','f941e.svg'],'events'=>['Events & Activities','19391.svg'],'leaderboards'=>['Leaderboards','ff9a2.svg'],'propose'=>['Propose Activity','4e5e9.svg'],'profile'=>['Profile','25646.svg'],'settings'=>['Settings','bc21d.svg']],
'scholar'=>['dashboard'=>['Dashboard','0596a.svg'],'tuition'=>['Tuition & Discount','0596a.svg'],'profile'=>['Profile','25646.svg'],'settings'=>['Settings','bc21d.svg']]];
$title=$page==='staff'?'Staff Workspace':($navs[$portal][$page][0]??ucwords(str_replace('-',' ',$page)));
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }} · iServe</title><link rel="stylesheet" href="{{ asset('app.css') }}"><script src="{{ asset('app.js') }}" defer></script><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="dark portal-{{ $portal }}"><aside class="sidebar" id="sidebar"><a class="portal-brand" href="/home"><x-logo/><strong>{{ $page==='staff'?'Staff Workspace':$labels[$portal] }}</strong></a><nav>
@if($page==='staff')<a class="active" href="/staff"><x-icon file="0596a.svg" :size="30"/><span>Workspace</span></a>@if(in_array(auth()->user()->role,['admin','assistant','sfu_head']))<a href="/staff/reports"><x-icon file="0596a.svg" :size="30"/><span>Semester Reports</span></a>@endif
@else
@foreach($navs[$portal] as $key=>[$label,$icon])<a class="{{ $page===$key?'active':'' }}" href="/portal/{{ $portal }}/{{ $key }}" title="{{ $label }}"><x-icon :file="$icon" :size="30"/><span>{{ $label }}</span></a>@endforeach
@endif
<a href="/home"><x-icon file="ad309.svg" :size="30"/><span>Return to iServe</span></a></nav></aside>
<div class="portal-shell"><header class="portal-header"><button class="mobile-nav" data-menu="sidebar" aria-label="Toggle sidebar">☰</button><div><h1>{{ $title }}</h1><small>@yield('subtitle','Your conduct, progress, and next steps at a glance.')</small></div><div class="header-actions"><a class="bell" href="/notifications" aria-label="Notifications"><x-icon file="d8b75.svg" :size="28"/><b>{{ auth()->user()->unreadNotifications()->count() }}</b></a><x-icon file="3d1d6.svg" :size="28"/><small>{{ now()->format('M j, Y') }}<br>{{ now()->format('l') }}</small></div></header><main class="portal-main">@include('components.flash')@yield('content')</main><footer class="portal-footer">iServe · Lead. Connect. Achieve.</footer></div><style>.live-attendance-camera{margin:12px 0;max-width:420px}.live-attendance-camera video,.live-attendance-camera img{width:100%;max-height:300px;object-fit:contain;border-radius:12px;background:#111827}.live-attendance-camera [hidden]{display:none!important}.live-attendance-camera p{font-size:14px}.live-camera-trigger{display:none!important}</style><script src="{{ asset('live-attendance-camera.js') }}" defer></script></body></html>



