@extends('workspace.layout')
@php
    $title = App\Models\ServiceRequest::kinds()[$item->kind] ?? 'Transaction Review';
    $reviewActions = $item->reviewActions(auth()->user());
    $showRejectionForm = $item->kind === 'leadership' && auth()->user()->role === 'admin' && isset($reviewActions['rejected']);
@endphp
@section('content')
@if($item->kind === 'activity' && in_array(auth()->user()->role, ['admin', 'slu_head'], true))
<a class="button quiet" href="{{ auth()->user()->role === 'admin' ? '/workspace/leadership' : '/workspace/proposals' }}">← Back</a>
@include('workspace.review-panel')
@else
<a class="button quiet" href="/workspace">← Back</a><div class="detail" style="margin-top:20px"><section><div class="panel"><h2>{{$item->kind==='scholarship'?'Application Review':'Selected Request Details'}}</h2><dl class="details-list"><div><dt>Reference Number</dt><dd>{{$item->ticket}}</dd></div><div><dt>Student Name</dt><dd>{{$item->user->name}}</dd></div><div><dt>Student ID</dt><dd>{{$item->user->student_id??'—'}}</dd></div><div><dt>Course / Year</dt><dd>{{$item->user->course??'—'}} · {{$item->user->year_level??'—'}}</dd></div><div><dt>Contact</dt><dd>{{$item->user->phone??'—'}}</dd></div><div><dt>Date Submitted</dt><dd>{{$item->created_at->format('M j, Y g:i A')}}</dd></div>@foreach($item->details as $key=>$value)@if(!is_array($value))<div><dt>{{ucwords(str_replace('_',' ',$key))}}</dt><dd class="text-body">{{is_bool($value)?($value?'Yes':'No'):$value}}</dd></div>@endif
@endforeach</dl></div>
<div class="panel">
<h2>{{$item->kind==='scholarship'?'Requirement Checklist':'Supporting Files'}}</h2>
@if($item->kind==='leadership')
@include('workspace.membership-attachments')
@else
@forelse($item->files??[] as $key=>$file)
@php($filename=is_array($file)?($file['name']??$key):basename($file??''))
<a class="file" href="{{route('staff.requests.attachment-view',['serviceRequest'=>$item,'key'=>$key])}}" target="_blank" rel="noopener"><span>▧ {{$filename?:$key}}</span><small>View / Download</small></a>
@empty
<p class="muted">No supporting files attached.</p>
@endforelse
@endif
</div>
<div class="panel"><h2>Submission Timeline</h2><div class="timeline"><article><strong>Submitted</strong><p>{{$item->created_at->format('M j, Y g:i A')}}</p></article>@foreach($history as $event)<article><strong>{{ucwords(str_replace(['request.','_'],['',' '],$event->action))}}</strong><p>{{$event->details}}</p><small>{{$event->name}} · {{$event->created_at}}</small></article>@endforeach</div></div></section><aside class="detail-sidebar"><div class="panel"><h2>Case Status</h2><p class="status {{$item->status}}">{{ucwords(str_replace('_',' ',$item->status))}}</p><p class="muted">Unit Head → Assistant → Admin</p><h3>{{ $item->kind === 'leadership' && $item->status === 'rejected' ? 'Reason for rejection' : 'Reviewer Remarks' }}</h3><p class="text-body">{{$item->review_note??'No review yet.'}}</p>@if(count($item->reviewActions(auth()->user())))<form method="post" action="/staff/requests/{{$item->id}}">@csrf<label class="field">{{auth()->user()->role==='assistant'?'Assistant Feedback':(auth()->user()->role==='admin'?'Admin Feedback':'Unit Head Remarks')}}<textarea name="review_note" required minlength="5" maxlength="3000" placeholder="Write your remarks here.....">{{old('review_note')}}</textarea></label>@if($item->kind==='scholarship')<label class="field">Discount Rate (%)<input type="number" name="discount_rate" min="1" max="100" value="{{old('discount_rate',$item->details['discount_rate']??'')}}"></label>@endif
@if($item->kind==='achievement'&&auth()->user()->role==='slu_head')<label class="field">Award Points<input type="number" name="awarded_points" min="-1000" max="1000" value="{{$item->details['awarded_points']??100}}"></label>@endif
@if($item->kind==='clearance'&&auth()->user()->role==='cvu_head')<label class="check"><input type="checkbox" name="identity_verified" value="1" @checked($item->details['identity_verified']??false)>Student identity verified</label><label class="check"><input type="checkbox" name="hymn_completed" value="1" @checked($item->details['hymn_completed']??false)>UCU Hymn requirement completed</label>@endif
<div class="actions">
@foreach($reviewActions as $value=>$label)
@continue($showRejectionForm && $value === 'rejected')
<button name="status" value="{{$value}}" class="{{in_array($value,['needs_revision','rejected'])?'outline':''}}">{{$label}}</button>
@endforeach
</div></form>@endif
@if($showRejectionForm)
@include('workspace.membership-rejection')
@endif
@if(auth()->user()->role===$item->office()&&in_array($item->status,['pending','in_review']))<h3>Save Review / Schedule</h3><form method="post" action="/workspace/request/{{$item->id}}/note">@csrf<label class="field">Unit Head Remarks<textarea name="review_note" required minlength="5" maxlength="3000"></textarea></label>@if(in_array($item->kind,['complaint','readmission']))<label class="field">Interview Date & Time<input type="datetime-local" name="interview_at"></label>@endif @if($item->kind==='clearance')<label class="field">Appointment Date<input type="date" name="appointment_date" value="{{$item->details['appointment_date']??''}}"></label><label class="field">Appointment Time<input type="time" name="schedule" value="{{$item->details['schedule']??''}}"></label><label class="check"><input name="identity_verified" type="checkbox" value="1">Student identity verified</label><label class="check"><input name="hymn_completed" type="checkbox" value="1">UCU Hymn requirement completed</label>@endif<div class="actions"><button class="outline">Save Review</button></div></form>@endif
@if($item->status==='approved'&&in_array($item->kind,['readmission','id-replacement','clearance']))<div class="actions"><a class="button" href="/workspace/request/{{$item->id}}/slip" target="_blank">Print Slip</a></div>@endif
@if($item->kind==='complaint'&&auth()->user()->role==='cvu_head'&&$item->status==='approved')<div class="actions"><a class="button outline" href="/workspace/community">Proceed to Community Service</a></div>@endif
@if(auth()->user()->role===$item->office()&&in_array($item->status,['approved','rejected','cancelled']))<h3>Record Actions</h3><form method="post" action="/workspace/request/{{$item->id}}/disposition">@csrf<label class="field">Unit Head Remarks<textarea name="review_note" minlength="5" maxlength="3000" required></textarea></label><div class="actions">@if($item->status==='approved'&&in_array($item->kind,['lost','found','id-replacement'])&&!($item->details['released']??false))<button name="action" value="release">Release Item / Slip</button>@endif<button class="outline" name="action" value="{{$item->details['archived']??false?'restore':'archive'}}">{{$item->details['archived']??false?'Restore Record':'Archive Record'}}</button></div></form>@endif
</div></aside></div>
@endif
@endsection
