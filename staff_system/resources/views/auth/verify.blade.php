@extends('layouts.blue')
@section('content')<div class="center-page"><h1>Verify Email</h1><section class="blue-card">@include('components.flash')<p>Use the verification link sent to <strong>{{ auth()->user()->email }}</strong> before submitting a transaction.</p><form method="post" action="/email/verification-notification">@csrf<button class="btn orange full">Resend verification email</button></form><a class="back-link" href="/home">Back to iServe</a></section></div>@endsection

