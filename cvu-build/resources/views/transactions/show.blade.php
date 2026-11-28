@extends('layouts.blue')
@php
    $complaintLabels = $q->kind === 'complaint' ? [
        'parent_guardian_name' => 'Parent or guardian name',
        'parent_guardian_relationship' => 'Relationship to student',
        'parent_guardian_phone' => 'Parent or guardian contact number',
        'interview_at' => 'Scheduled meeting',
    ] : [];
@endphp
@section('content')<div class="center-page wide-form"><h1>{{ App\Models\ServiceRequest::kinds()[$q->kind] }}</h1><section class="blue-card">@include('components.flash')<div class="success-symbol">{{ $q->status==='approved'?'✓':'↗' }}</div><h2 class="center">{{ $q->ticket }}</h2><p class="center"><span class="badge {{ $q->status }}">{{ ucwords(str_replace('_',' ',$q->status)) }}</span></p><p class="center">Submitted {{ $q->created_at->format('M j, Y · g:i A') }}</p><dl class="detail-list"><dt>Applicant</dt><dd>{{ $q->user->name }}</dd>@foreach($q->details as $key=>$value)<dt>{{ $complaintLabels[$key] ?? ucwords(str_replace('_',' ',$key)) }}</dt><dd>{{ is_array($value)?collect($value)->map(fn($part)=>is_scalar($part)?$part:json_encode($part))->implode(" · "):$value }}</dd>@endforeach</dl>
@if($q->review_note)<div class="notice"><strong>{{ $q->kind === 'leadership' && $q->status === 'rejected' ? 'Reason for rejection' : 'Office response' }}</strong><p>{{ $q->review_note }}</p></div>@endif
@if($q->kind === 'readmission' && $q->status === 'approved')
    <a class="btn orange" href="{{ route('readmission.slip', $q) }}">View and Print Re-Admission Slip</a>
@endif
@if($q->kind === 'id-replacement' && $q->status === 'approved')
    <p class="notice" role="status">{{ match(true) {
        !empty($q->details['released']) => 'Your replacement ID has been released.',
        !empty($q->details['reprinted_at']) => 'Your replacement ID has been reprinted. Contact the CVU office for pickup.',
        default => 'Your request is approved. You will receive an update when the replacement ID is reprinted.',
    } }}</p>
@endif
@if($q->files)<h3>Documents</h3>@foreach($q->files as $key=>$file)<a class="file-link" href="/requests/{{ $q->id }}/files/{{ $key }}">{{ $file['name'] }} ↗</a>@endforeach
@endif
@if($q->user_id===auth()->id()&&$q->kind==='activity'&&$q->status==='approved'&&empty($q->details['completion_report']))
<a class="btn secondary" href="{{ route('activity.completion', $q) }}">After-event report</a>
@endif
@if($q->user_id===auth()->id()&&$q->status==='needs_revision')<form method="post" action="/requests/{{ $q->id }}/revise" enctype="multipart/form-data">@csrf<label>Response to the reviewer<textarea name="revision" rows="4" required minlength="10"></textarea></label>
@php($addAffidavit = false)
@if($q->kind==='id-replacement')
    @include('transactions.id-replacement-payment', ['paymentDetails'=>$q->details])
    @php($addAffidavit = in_array($q->details['replacement_reason'] ?? null, config('portal.id_replacement_affidavit_reasons'), true) && empty($q->files['evidence']))
@endif
@foreach(!empty($q->details['completion_report']) ? ['completion_report'=>'After-event report'] : array_merge(config('portal.documents.'.$q->kind,[]),collect($q->files??[])->map(fn($file,$key)=>ucwords(str_replace('_',' ',$key)))->all()) as $key=>$label)<label>{{ $addAffidavit && $key==='evidence' ? 'Affidavit of loss' : 'Replace '.$label.' (optional)' }}<input type="file" name="documents[{{ $key }}]" @required($addAffidavit && $key==='evidence') accept="{{ $key==='completion_report' ? '.pdf,.docx,.jpg,.jpeg,.png' : '.pdf,.jpg,.jpeg,.png' }}"></label>@endforeach<button class="btn orange full">Resubmit for Review</button></form>@endif
@php($reviewActions=$q->reviewActions(auth()->user()))
@if($reviewActions)
<fieldset><legend>Review Request</legend><form method="post" action="/staff/requests/{{ $q->id }}">@csrf<label>Decision<select name="status">
@foreach($reviewActions as $value=>$label)
<option value="{{ $value }}">{{ $label }}</option>
@endforeach
</select></label>
@if($q->kind==='scholarship'&&in_array(auth()->user()->role,['sfu_head','admin']))
<label>Discount Rate (%)<input name="discount_rate" type="number" min="1" max="100" value="{{ $q->details['discount_rate']??'' }}"></label>
@endif
<label>Review Note<textarea name="review_note" minlength="5" required rows="3"></textarea></label><button class="btn orange full">Save Decision</button></form></fieldset>
@endif
<section><h3>Review History</h3><p class="muted">Unit Head → Assistant → Admin final approval</p>
@forelse($history as $entry)
<div class="record-row"><strong>{{ $entry->actor_name??'Staff' }} · {{ str_replace('_',' ',$entry->actor_role??'') }}</strong><span>{{ str_replace(['request.','_'],['',' '],$entry->action) }} · {{ $entry->created_at }}</span><p>{{ $entry->details }}</p></div>
@empty
<p class="muted">No staff review yet.</p>
@endforelse
</section>
@if($q->user_id===auth()->id()&&in_array($q->status,['pending','needs_revision']))<form method="post" action="/requests/{{ $q->id }}/cancel" data-confirm="Cancel this request?">@csrf<button class="btn secondary full">Cancel Request</button></form>@endif
<a class="back-link" href="{{ auth()->user()->isStaff()?'/staff':'/home' }}">Back to {{ auth()->user()->isStaff()?'Staff Workspace':'iServe' }}</a></section></div>@endsection

