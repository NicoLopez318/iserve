@extends('layouts.blue')
@section('content')<div class="center-page"><h1>Reset Password</h1><section class="blue-card">@include('components.flash')<p>We'll send a password reset link to your registered email.</p><form method="post" action="/forgot-password">@csrf<label>Email<input name="email" type="email" required></label><button class="btn orange full">Send reset link</button></form><a class="back-link" href="/login">Back to login</a></section></div>@endsection

