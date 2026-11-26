@php
    $reviewer = auth()->user();
    $reviewActions = $item->reviewActions($reviewer);
    $proposalReview = $item->kind === 'activity' && in_array($reviewer->role, ['slu_head', 'admin'], true);
    $showRejectionForm = $item->kind === 'leadership' && $reviewer->role === 'admin' && isset($reviewActions['rejected']);
@endphp
<section @class(['panel compact-review', 'proposal-review' => $proposalReview, 'slu-membership-review' => $membershipReview ?? false, 'slu-proposal-review' => $sluProposalReview ?? false, 'slu-achievement-review' => $sluAchievementReview ?? false])
         aria-label="{{ $proposalReview ? 'Activity proposal' : 'Selected transaction review' }}">
@if($membershipReview ?? false)
@include('workspace.organization-membership-review')
@elseif($sluProposalReview ?? false)
@include('workspace.slu-proposal-review')
@elseif($sluAchievementReview ?? false)
@include('workspace.slu-achievement-review')
@else
@if($proposalReview)
@include('workspace.proposal-review-details')
@else
<h2>{{$item->kind==='scholarship'?'Application Review':'Selected Request Details'}}</h2>
<strong>{{$item->ticket}}</strong><p>{{$item->user->name}}<small>{{$item->user->student_id}} · {{$item->user->course}}</small></p>
<span class="status {{$item->status}}">{{ucwords(str_replace('_',' ',$item->status))}}</span>
<dl class="compact-details">
@foreach($item->details as $key=>$value)
@if(!is_array($value)&&!in_array($key,['identity_verified','hymn_completed','archived','released']))
<dt>{{ucwords(str_replace('_',' ',$key))}}</dt><dd>{{is_bool($value)?($value?'Yes':'No'):$value}}</dd>
@endif
@endforeach
</dl>
<h3>{{$item->kind==='scholarship'?'Requirement Checklist':'Supporting Files'}}</h3>
@if($item->kind==='leadership')
@include('workspace.membership-attachments')
@else
@forelse($item->files??[] as $key=>$file)
@php($filename=is_array($file)?($file['name']??$key):basename($file??''))
<a class="file" href="{{route('staff.requests.attachment-view',['serviceRequest'=>$item,'key'=>$key])}}" target="_blank" rel="noopener">{{$filename?:$key}}<small>View / Download</small></a>
@empty
<p class="muted">No supporting files attached.</p>
@endforelse
@endif
@endif
@if(!$proposalReview || $item->review_note)
<h3>{{ $item->kind === 'leadership' && $item->status === 'rejected' ? 'Reason for rejection' : 'Reviewer Remarks' }}</h3><p class="text-body">{{$item->review_note??'No review yet.'}}</p>
@endif
@if($reviewActions)
<form method="post" action="/staff/requests/{{$item->id}}" @if($proposalReview) class="proposal-decision" @endif>@csrf
@if($proposalReview)
<h3>Review decision</h3>
@error('workflow')<p class="errors" role="alert">{{ $message }}</p>@enderror
@endif
<label class="field">{{ $proposalReview || $item->kind === 'scholarship' ? 'Reviewer Remarks' : 'Feedback' }}
<textarea name="review_note" required minlength="5" maxlength="3000" @if($proposalReview) placeholder="Explain your review decision" @endif>{{old('review_note')}}</textarea></label>
@if($proposalReview)
@error('review_note')<p class="errors" role="alert">{{ $message }}</p>@enderror
@endif
@if($item->kind==='scholarship')<label class="field">Discount Rate (%)<input type="number" name="discount_rate" min="1" max="100" value="{{old('discount_rate',$item->details['discount_rate']??'')}}"></label>@endif
@if($item->kind==='achievement'&&auth()->user()->role==='slu_head')<label class="field">Award Points<input type="number" name="awarded_points" min="-1000" max="1000" value="{{old('awarded_points',$item->details['awarded_points']??$rules['achievement']??100)}}"></label>@endif
@if($item->kind==='clearance'&&auth()->user()->role==='cvu_head')
<label class="check"><input type="checkbox" name="identity_verified" value="1" @checked(old('identity_verified',$item->details['identity_verified']??false))>Student identity verified</label>
<label class="check"><input type="checkbox" name="hymn_completed" value="1" @checked(old('hymn_completed',$item->details['hymn_completed']??false))>UCU Hymn requirement completed</label>
@endif
<div class="actions">
@foreach($reviewActions as $value=>$label)
@continue($showRejectionForm && $value === 'rejected')
<button name="status" value="{{$value}}" class="{{in_array($value,['needs_revision','rejected','in_review'])?'outline':''}}">{{ $proposalReview && $value === 'unit_approved' ? 'Endorse to Assistant' : $label }}</button>
@endforeach
</div></form>
@endif
@if($showRejectionForm)
@include('workspace.membership-rejection')
@endif
@endif
@if(auth()->user()->role===$item->office()&&in_array($item->status,['pending','in_review'])&&in_array($item->kind,['complaint','readmission','clearance']))
<details><summary>Save Review / Schedule</summary><form method="post" action="/workspace/request/{{$item->id}}/note">@csrf
<label class="field">Remarks<textarea name="review_note" required minlength="5" maxlength="3000">{{old('review_note',$item->review_note)}}</textarea></label>
@if($item->kind==='clearance')
<label class="field">Appointment Date<input type="date" name="appointment_date" value="{{$item->details['appointment_date']??''}}"></label>
<label class="field">Appointment Time<input type="time" name="schedule" value="{{$item->details['schedule']??''}}"></label>
@else
<label class="field">Interview Date & Time<input type="datetime-local" name="interview_at"></label>
@endif
<button class="outline">Save Review</button></form></details>
@endif
@if($item->status==='approved'&&in_array($item->kind,['readmission','id-replacement','clearance']))
<a class="button" href="/workspace/request/{{$item->id}}/slip" target="_blank" rel="noopener">Print Slip</a>
@endif
@if($item->status==='approved'&&$item->kind==='complaint'&&auth()->user()->role==='cvu_head')
<a class="button outline" href="/workspace/community">Proceed to Community Service</a>
@endif
@if(auth()->user()->role===$item->office()&&in_array($item->status,['approved','rejected','cancelled']))
@include('workspace.review-record-actions')
@endif
@include('workspace.review-history')
</section>
