@extends('cvu.layout')
@section('navigation')<a class="home-icon" href="/transactions" aria-label="Return to Select Transaction">⌂</a>@endsection
@section('content')
@if(!$u->values_verified_at)
<section class="flow-card compact"><h1>Confirm Student ID</h1><form method="post" action="/community/confirm">@csrf<label>Student ID<input name="student_id" value="{{ old('student_id',$u->student_id) }}" autocomplete="off" required></label><button class="orange">Confirm Student ID</button></form></section>
@elseif(request('linked'))
<section class="flow-card result" data-link-result role="status"><div class="success-symbol">✓</div><h1>Your student account<br>has been successfully linked.</h1><p>Checking your student record…</p><a class="orange" href="/community-link">View Record</a></section>
@elseif(!$a||$a->status!=='active')
<section class="flow-card result"><div class="info-symbol">!</div><h1>No Record Found</h1><p>Proceed to the CSLD Office for more concerns.</p><a class="orange" href="/">Back to Homepage</a></section>
@else
<section class="flow-card result"><div class="warning-symbol">!</div><h1>Active Record Found</h1><a class="orange" href="/portal/values">Continue to Community Service</a></section>
@endif
@endsection
