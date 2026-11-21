<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>My Profile · iServe</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('account-profile.css') }}"><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="account-profile">
<header class="account-header"><a class="account-brand" href="/" aria-label="iServe home"><x-logo/><strong>iServe</strong></a><nav class="account-actions" aria-label="Account navigation"><a href="/">Home</a><form action="/logout" method="post">@csrf<button type="submit">Log out</button></form></nav></header>
<main class="account-main"><h1>My Profile</h1>@include('components.flash')@yield('content')</main>
<footer class="account-footer">iServe · Lead. Connect. Achieve.</footer>
</body></html>