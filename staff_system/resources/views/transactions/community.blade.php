@extends('layouts.blue')
@section('content')<div class="center-page"><h1>Community Service</h1><section class="blue-card">@include('components.flash')
@if(!auth()->user()->hasVerifiedEmail())<h2>Verify your student account</h2><p>Use your email verification link to confirm your identity.</p><a class="btn orange full" href="/email/verify">Send verification link</a>
@elseif(auth()->user()->assignment)<div class="success-symbol">✓</div><h2>Active Record Found</h2><p>{{ auth()->user()->assignment->record_number }} · {{ auth()->user()->assignment->status }}</p><a class="btn orange full" href="/portal/values/community-service">Continue Community Service</a>
@else<h2>No Record Found</h2><p>You have no assigned community service record. Contact the Community Values Unit if you believe you have an outstanding record.</p><a class="btn orange full" href="/home">Back to iServe</a>@endif
</section></div>@endsection

