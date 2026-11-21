<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','iServe')</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('design-refinements.css') }}"><script src="{{ asset('app.js') }}" defer></script><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="blue @yield('bodyClass')"><div class="blue-backdrop" aria-hidden="true"><img class="blue-wave" src="{{ asset('design-assets/2a5ad.svg') }}" alt=""></div>
<header class="blue-header"><a href="{{ auth()->check()?'/':'/login' }}" aria-label="iServe home"><x-logo /></a><button class="menu-button" aria-label="Open navigation" aria-expanded="false" data-menu="blue-menu"><span></span><span></span><span></span></button>
<nav id="blue-menu" class="menu-popover" hidden>@auth<a href="/">Home</a>@if(auth()->user()->isStaff())<a href="/staff">Staff workspace</a>@endif<form action="/logout" method="post">@csrf<button>Log out</button></form>@else<a href="/login">Log in</a><a href="/register">Sign up</a>@endauth</nav></header>
<main>@yield('content')</main>
<footer class="blue-footer"><x-icon file="9148c.svg" :size="54"/><div>Secure Login<br><small>Secure account access</small></div></footer></body></html>

