@extends('layouts.blue')
@section('content')<div class="center-page"><h1>Verify Sign-in</h1><section class="blue-card">@include('components.flash')<p>Enter the six-digit code sent to your email. It expires in 10 minutes.</p><form method="post" action="/two-factor">@csrf<label>Verification code<input name="code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required></label><button class="btn orange full">Verify</button></form><a class="back-link" href="/login">Start again</a></section></div>@endsection

