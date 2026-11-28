@extends('layouts.blue')
@section('title',App\Models\ServiceRequest::kinds()[$kind].' · iServe')
@section('content')<div class="center-page wide-form"><h1>{{ App\Models\ServiceRequest::kinds()[$kind] }}</h1><section class="blue-card">@include('components.flash')<div class="steps"><span class="active">1. Basic information</span><span>2. Details & documents</span><span>3. Confirmation</span></div><form data-transaction-wizard @if($errors->hasAny(['details.other_complaint','details.parent_guardian_*']) || ($kind==='id-replacement' && $errors->any())) data-wizard-start="1" @endif action="/apply/{{ $kind }}" method="post" enctype="multipart/form-data">@csrf
<fieldset data-form-step="0"><legend>Basic Information</legend><div class="form-grid"><label>Full Name<input value="{{ auth()->user()->name }}" readonly></label><label>{{ auth()->user()->account_type==='student'?'Student ID':'Account Type' }}<input value="{{ auth()->user()->student_id??ucfirst(auth()->user()->account_type) }}" readonly></label>@if(auth()->user()->account_type==='student')<label>Course<input value="{{ auth()->user()->course }}" readonly></label><label>Year Level<input value="{{ auth()->user()->year_level }}" readonly></label>@endif</div><a href="/profile/setup">Edit profile information</a></fieldset>
<fieldset data-form-step="1"><legend>Details</legend><div class="form-grid">
@foreach(config('portal.fields.'.$kind,[]) as $name=>$type)<label class="{{ $type==='textarea'?'span-2':'' }}">{{ ucwords(str_replace('_',' ',$name==='organization_id'?'organization':($name==='event_id'?'seminar':$name))) }}
@if($type==='textarea')<textarea name="details[{{ $name }}]" rows="4" required>{{ old('details.'.$name,$draftDetails[$name]??null) }}</textarea>
@elseif(str_starts_with($type,'select:'))<select name="details[{{ $name }}]" required><option value="">Choose an option</option>@foreach(explode(',',substr($type,7)) as $option)<option value="{{ $option }}" @selected(old('details.'.$name,$draftDetails[$name]??null)===$option)>{{ $kind==='complaint' && $name==='complaint_type' && $option==='Other' ? 'Others' : $option }}</option>@endforeach</select>
@elseif(in_array($type,['organization','seminar']))<select name="details[{{ $name }}]" required><option value="">Choose {{ $type }}</option>@foreach($type==='organization'?$organizations:$seminars as $item)<option value="{{ $item->id }}" @selected(old('details.'.$name,$draftDetails[$name]??null)==$item->id)>{{ $type==='organization'?$item->name:($item->title.' · '.$item->starts_at->format('M j, Y')) }}</option>@endforeach</select>
@else<input type="{{ $type }}" name="details[{{ $name }}]" value="{{ old('details.'.$name,$draftDetails[$name]??null) }}" @if($type==='number') min="1" max="{{ $kind==='activity'&&$name==='capacity' ? config('portal.activity_capacity_max') : ($name==='points'?1000:5000) }}" @endif required>@endif</label>
@if($kind==='complaint' && $name==='complaint_type')
    @php($customComplaint = old('details.other_complaint'))
    <label data-other-complaint>Specify your complaint type
        <input type="text" name="details[other_complaint]" maxlength="500"
            value="{{ is_string($customComplaint) ? $customComplaint : '' }}" aria-describedby="other-complaint-hint"
            @if($errors->has('details.other_complaint')) aria-invalid="true" @endif>
        <small id="other-complaint-hint">Required when you choose Others.</small>
    </label>
@endif
@endforeach
@if($kind==='id-replacement')@include('transactions.id-replacement-payment')@endif
@foreach(config('portal.optional_fields.'.$kind,[]) as $name=>$type)<label>{{ ucwords(str_replace('_',' ',$name)) }} (optional)<input type="{{ $type }}" name="details[{{ $name }}]" value="{{ old('details.'.$name,$draftDetails[$name]??null) }}"></label>@endforeach
@if($kind==='activity')
    <input type="hidden" name="details[off_campus]" value="0">
    <label class="check"><input type="checkbox" name="details[off_campus]" value="1" @checked(old('details.off_campus', $draftDetails['off_campus'] ?? false))> Off-campus activity</label>
@endif
</div></fieldset>
@if($kind==='complaint' && auth()->user()->account_type==='student')
    <fieldset data-form-step="1">
        <legend>Parent or guardian contact (optional)</legend>
        <p class="muted">Add a contact the CVU can use if a case conference is needed.</p>
        <div class="form-grid">
            @foreach(['parent_guardian_name'=>['Full name',120], 'parent_guardian_relationship'=>['Relationship to you',60], 'parent_guardian_phone'=>['Contact number',40]] as $key => [$label,$max])
                @php($value = old('details.'.$key))
                <label>{{ $label }}
                    <input type="{{ $key==='parent_guardian_phone' ? 'tel' : 'text' }}" name="details[{{ $key }}]"
                        maxlength="{{ $max }}" value="{{ is_string($value) ? $value : '' }}">
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
@if(config('portal.documents.'.$kind))<fieldset data-form-step="1"><legend>Upload Documents</legend>@if($kind==='id-replacement')<p>Upload an affidavit of loss for a lost or stolen ID. For a damaged ID, you may attach a photo.</p>@endif<p class="muted">PDF, PNG, or JPG · up to {{ $kind==='activity'?10:5 }} MB per file. Only you and the assigned office can access these documents.</p>@foreach(config('portal.documents.'.$kind) as $key=>$label)@if($kind==='activity'&&in_array($key,['pre_intent','post_documentation']))<h3>{{ $key==='pre_intent'?'Pre-Activity Form':'Post-Activity Form' }}</h3><p>For activities conducted outside the school / university</p>@endif<label class="upload-row">{{ $label }}<input type="file" name="documents[{{ $key }}]" @if($kind==='id-replacement') data-affidavit-reasons="{{ implode(',',config('portal.id_replacement_affidavit_reasons')) }}" @endif accept=".pdf,.jpg,.jpeg,.png" data-max-mb="{{ $kind==='activity'?10:5 }}" @required(!in_array($kind.'.'.$key,config('portal.optional_documents')))></label>@endforeach</fieldset>@endif
<label data-form-step="1" class="check declaration"><input type="checkbox" name="declaration" value="1" required>I confirm the information is accurate and authorize the university to review this request.</label>@if($kind==='activity')<div data-form-step="1">@if($draft)<p class="muted">Draft last saved {{ $draft->updated_at }}</p>@endif @foreach($draftFiles as $file)<p class="file-link">Saved document: {{ $file['name'] }}</p>@endforeach<button type="submit" class="btn secondary" formaction="/proposal-draft" formnovalidate>Save Draft</button></div>@endif<div class="wizard-controls"><button type="button" class="btn secondary" data-wizard-back hidden>Back</button><button type="button" class="btn orange full" data-wizard-next>Next</button><button class="btn orange full" data-submit data-form-step="1">Submit Request</button></div><a class="back-link" href="/home">Back to iServe</a></form></section></div>@endsection

