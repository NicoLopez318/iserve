@extends('cvu.layout')
@section('navigation')<a class="home-icon" href="/transactions" aria-label="Return to Select Transaction">⌂</a>@endsection
@section('content')
@if($kind==='seminar')
<section class="seminar-catalog"><h1>Seminars</h1>
@forelse($events as $e)
@php($joined=$e->attendances->isNotEmpty())
@php($registrationClosed=!empty($e->metadata['deadline'])&&now()->gt(Carbon\Carbon::parse($e->metadata['deadline'])->endOfDay()))
<article class="seminar-offer"><div class="seminar-poster"><small>Urdaneta City University<br>Center for Student Leadership and Development<br>Character & Values</small><h2>{{ $e->title }}</h2><strong>{{ $e->starts_at->format('M j, Y') }}</strong><p>{{ $e->description }}</p><p>{{ $e->venue }}</p></div>
@if($joined)
<p class="notice" role="status">You have joined this seminar. Attendance status: {{ str_replace('_',' ',$e->attendances->first()->status) }}.</p>
@elseif($registrationClosed||$e->registration_count >= $e->capacity)
<p class="notice">{{ $registrationClosed?'Registration has closed.':'This seminar is full.' }}</p>
@else
<button class="orange" type="button" data-join="{{ $e->id }}">Join Now</button>
<form class="flow-card" data-seminar-form="{{ $e->id }}" @if((string)old('details.event_id',request('event'))!==(string)$e->id) hidden @endif method="post" action="/cvu/submit/seminar">@csrf
<input type="hidden" name="details[event_id]" value="{{ $e->id }}"><h2>Attendance Sheet</h2><h3>Student Information</h3><div class="fields">@include('cvu.identity')</div>
@foreach(['I confirm that the student information above belongs to me and is correct.',$e->starts_at->isFuture()?'I confirm that I will attend this seminar session.':'I confirm that I attended this seminar session.','I understand that my attendance is subject to verification by the event facilitator.'] as $text)
<label class="check"><input type="checkbox" name="confirmations[]" value="1" required>{{ $text }}</label>
@endforeach
<button class="orange" data-prevent-duplicate>Submit</button></form>
@endif
</article>
@empty
<section class="flow-card"><h2>No Upcoming Seminars</h2><p>Published seminars will appear here when registration opens.</p><a class="orange" href="/transactions">Done</a></section>
@endforelse
</section>
@else
<form class="flow-card {{ $kind==='readmission'?'readmission-form':'' }}" method="post" action="/cvu/submit/{{ $kind }}" enctype="multipart/form-data">@csrf
@if($kind==='readmission')
<section data-basic><h1>Basic Information</h1><div class="fields">@include('cvu.identity')</div><button type="button" class="orange" data-next>Next</button></section>
<section data-details @if($errors->hasAny(['details.*','confirmations','confirmations.*','evidence','workflow']) && !$errors->hasAny(['name','student_id','contact','course','year_level'])) data-return-to-details @endif hidden><h1>Re-Admission Request / Explanation Letter</h1><h3>Basic Information</h3><div data-identity-summary></div><h3>Subject Information</h3><div class="fields">
@foreach(['instructor'=>['Name of Instructor',120],'semester'=>['Semester',40],'academic_year'=>['Academic Year',40],'subject_code'=>['Subject Code',40],'subject'=>['Subject',120],'schedule'=>['Subject Schedule (Day & Time)',120]] as $key=>[$label,$max])<label>{{ $label }}<input name="details[{{ $key }}]" value="{{ old('details.'.$key) }}" maxlength="{{ $max }}" required disabled></label>@endforeach</div><h3>Detailed Explanation</h3><label>Explanation<textarea name="details[description]" rows="5" minlength="10" maxlength="5000" required disabled placeholder="Explain the circumstances that resulted in your absence or removal from class, relevant dates, actions taken, and why you request re-admission.">{{ old('details.description') }}</textarea></label><label>Supporting evidence (optional)<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" disabled></label>
@foreach(['I agree to attend the scheduled interview and provide any clarification needed regarding my request.','I understand that the interview is part of the evaluation process and does not guarantee approval.'] as $text)<label class="check"><input type="checkbox" name="confirmations[]" value="1" @checked((string)old('confirmations.'.$loop->index)==='1') required disabled>{{ $text }}</label>@endforeach<div class="actions"><button type="button" data-back>Back</button><button class="orange" data-readmission-submit disabled>Submit</button></div></section>
@else
<h1>Appointment of Clearance</h1><h3>Student Information</h3><div class="fields">@include('cvu.identity')</div><h3>Appointment Details</h3><div class="fields"><label>Select Date<select data-slot-date required><option value="">Select date</option>@foreach(collect($slots)->filter(fn($s)=>($s['open']??false)&&$s['date']>=today()->toDateString())->pluck('date')->unique()->sort() as $date)<option>{{ $date }}</option>@endforeach</select></label><label>Select Time<select name="details[appointment_slot]" data-slot-time required><option value="">Select time</option>@foreach($slots as $key=>$slot)@if(($slot['open']??false)&&$slot['date']>=today()->toDateString())<option value="{{ $key }}" data-date="{{ $slot['date'] }}">{{ $slot['start'] }}</option>@endif
@endforeach</select></label></div>
@if(!collect($slots)->contains(fn($s)=>($s['open']??false)&&$s['date']>=today()->toDateString()))<p>No appointment slots are currently open. Please contact the CSLD office.</p>@endif
<h3>Reminder</h3><aside class="reminder">Please arrive on time for your scheduled appointment.<br>Make sure you have memorized the UCU Hymn.<br>Confirmation is sent through your registered account.<br>Your appointment is subject to confirmation.</aside>
@foreach(['I have reviewed the information provided and confirm that it is correct.','I understand that my appointment is subject to availability and confirmation.','I agree to follow the rules and requirements of the clearance process.'] as $text)<label class="check"><input type="checkbox" name="confirmations[]" value="1" required>{{ $text }}</label>@endforeach<button class="orange">Submit</button>
@endif</form>
@endif
@endsection
