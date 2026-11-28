@extends('layouts.blue')
@section('content')<div class="center-page wide-form"><h1>{{ App\Models\ServiceRequest::kinds()[$q->kind] }}</h1><section class="blue-card">@include('components.flash')<div class="success-symbol">{{ $q->status==='approved'?'✓':'↗' }}</div><h2 class="center">{{ $q->ticket }}</h2><p class="center"><span class="badge {{ $q->status }}">{{ ucwords(str_replace('_',' ',$q->status)) }}</span></p><p class="center">Submitted {{ $q->created_at->format('M j, Y · g:i A') }}</p><dl class="detail-list"><dt>Applicant</dt><dd>{{ $q->user->name }}</dd>@foreach($q->details as $key=>$value)<dt>{{ ucwords(str_replace('_',' ',$key)) }}</dt><dd>{{ $value }}</dd>@endforeach</dl>
@if($q->review_note)<div class="notice"><strong>Office response</strong><p>{{ $q->review_note }}</p></div>@endif
@if($q->files)<h3>Documents</h3>@foreach($q->files as $key=>$file)<a class="file-link" href="/requests/{{ $q->id }}/files/{{ $key }}">{{ $file['name'] }} ↗</a>@endforeach
@endif
@if($q->user_id===auth()->id()&&$q->status==='needs_revision')<form method="post" action="/requests/{{ $q->id }}/revise" enctype="multipart/form-data">@csrf<label>Response to the reviewer<textarea name="revision" rows="4" required minlength="10"></textarea></label>
@php($addAffidavit = false)
@if($q->kind==='id-replacement')
    @include('transactions.id-replacement-payment', ['paymentDetails'=>$q->details])
    @php($addAffidavit = in_array($q->details['replacement_reason'] ?? null, config('portal.id_replacement_affidavit_reasons'), true) && empty($q->files['evidence']))
@endif
@foreach(($q->details['completion_report']??false)?['completion_report'=>'Completion Report']:config('portal.documents.'.$q->kind,[]) as $key=>$label)<label>{{ $addAffidavit && $key==='evidence' ? 'Affidavit of loss' : 'Replace '.$label.' (optional)' }}<input type="file" name="documents[{{ $key }}]" @required($addAffidavit && $key==='evidence') accept=".pdf,.jpg,.jpeg,.png,.docx"></label>@endforeach<button class="btn orange full">Resubmit for Review</button></form>@endif
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

