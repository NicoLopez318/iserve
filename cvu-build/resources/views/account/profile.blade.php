@extends('layouts.account')
@section('content')
<section class="account-card" aria-label="Personal details">
<div class="account-summary">
@if($u->avatar)<img class="account-photo" src="{{ url('/avatar') }}" alt="Your profile photo">@else<div class="account-photo account-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($u->name,0,1)) }}</div>@endif
<div><h2>{{ $u->name }}</h2><p>{{ ucfirst($u->account_type) }} account</p></div>
<a class="account-edit" href="/profile/setup">Edit Profile</a>
</div>
<dl class="account-details">
<div><dt>Email</dt><dd>{{ $u->email }}</dd></div>
<div><dt>Username</dt><dd>{{ $u->username ?: 'Not provided' }}</dd></div>
<div><dt>Contact number</dt><dd>{{ $u->phone ?: 'Not provided' }}</dd></div>
@if($u->account_type==='student')
<div><dt>Student ID</dt><dd>{{ $u->student_id ?: 'Not provided' }}</dd></div>
<div><dt>Course</dt><dd>{{ $u->course ?: 'Not provided' }}</dd></div>
<div><dt>Year level</dt><dd>{{ $u->year_level ?: 'Not provided' }}</dd></div>
<div><dt>Organization</dt><dd>{{ $u->organization?->name ?? 'No organization yet' }}</dd></div>
<div><dt>Position</dt><dd>{{ $u->position ?: 'Not assigned' }}</dd></div>
@elseif($u->account_type==='employee')
<div><dt>Employee ID</dt><dd>{{ $u->employee_id ?: 'Not provided' }}</dd></div>
<div><dt>College / Office</dt><dd>{{ $u->office ?: 'Not provided' }}</dd></div>
@elseif($u->account_type==='guardian')
<div><dt>Student name</dt><dd>{{ $u->ward_name ?: 'Not provided' }}</dd></div>
<div><dt>Student ID</dt><dd>{{ $u->ward_student_id ?: 'Not provided' }}</dd></div>
@endif
</dl>
</section>
@endsection
