@extends('layouts.blue')
@section('title','Sign up · iServe')
@section('bodyClass','auth-screen')
@section('content')
<div class="auth-hero"><h1><span>i</span>Serve</h1><h2>Lead. Connect. Achieve.</h2><p>Designed to simplify student participation, communication, and campus engagement.</p></div>
<section class="auth-form"><h2 class="register-title">Sign up</h2>@include('components.flash')<form action="/register" method="post">@csrf
<label>Email<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="Enter your email" required></label><label>Username<input name="username" value="{{ old('username') }}" autocomplete="username" placeholder="Choose a username" required></label>
<label>Password<span class="password-field"><input name="password" type="password" minlength="10" autocomplete="new-password" placeholder="At least 10 characters" required><button type="button" data-password aria-label="Show password"><x-icon file="edcb5.svg" :size="22"/></button></span></label><label>Confirm Password<input name="password_confirmation" type="password" autocomplete="new-password" required></label><button class="btn orange full">Sign up</button></form>@if(config('services.google.client_id'))<p class="or">Or</p><a class="btn google full" href="/auth/google"><img src="{{ asset('design-assets/68ab8.png') }}" alt="">Sign up with Google</a>@endif<p class="auth-link">Already have an account? <a href="/login">Log in</a></p></section>
@endsection

