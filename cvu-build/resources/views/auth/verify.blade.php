@extends('layouts.blue')
@section('title', 'Verify Email · iServe')
@section('content')
<div class="center-page">
    <h1>Verify Email</h1>
    <section class="blue-card">
        @include('components.flash')
        @if(auth()->user()->hasVerifiedEmail())
            <p><strong>{{ auth()->user()->email }}</strong> is verified.</p>
            @if(auth()->user()->profile_complete)
                <a class="btn orange full" href="/#services">Browse services</a>
            @else
                <a class="btn orange full" href="/profile/setup">Complete your profile</a>
            @endif
        @else
            <p>Check <strong>{{ auth()->user()->email }}</strong> for your verification link. Open it to verify your email and continue to your transactions.</p>
            <p>If you cannot find the email, check Spam or request another link.</p>
            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn orange full">Resend verification email</button>
            </form>
        @endif
        <a class="back-link" href="/home">Back to iServe</a>
    </section>
</div>
@endsection

