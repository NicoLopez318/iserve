@extends('layouts.blue')
@section('title','Welcome Back · iServe')
@section('bodyClass','auth-screen')
@section('content')
<div class="auth-hero"><h1><span>i</span>Serve</h1><h2>Level Up Your Campus Journey</h2><p>Designed to simplify student participation, communication, and campus engagement.</p></div>
<section class="auth-form login-form"><h2>Welcome<br> Back</h2>@include('components.flash')
<form action="/login" method="post">@csrf<label>Email or Username<input name="login" value="{{ old('login') }}" autocomplete="username" placeholder="Enter your email or username" required autofocus></label><label>Password<span class="password-field"><input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" data-password aria-label="Show password"><x-icon file="edcb5.svg" :size="22"/></button></span></label><div class="form-options"><label class="check"><input type="checkbox" name="remember" value="1">Remember me</label><a href="/forgot-password">Forgot password?</a></div><button class="btn orange full">Log in</button></form>
@if(config('services.google.client_id'))<p class="or">Or</p><a class="btn google full" href="/auth/google"><img src="{{ asset('design-assets/68ab8.png') }}" alt="">Sign in with Google</a>@endif
<p class="auth-link">Don't have an account? <a href="/register">Sign up</a></p></section>
@endsection

