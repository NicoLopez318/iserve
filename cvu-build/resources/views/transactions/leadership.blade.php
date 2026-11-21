<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Leadership Information · iServe</title>
<link rel="stylesheet" href="{{ asset('app.css') }}">
<link rel="stylesheet" href="{{ asset('leadership-onboarding.css') }}?v=20261120">
<link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="leadership-onboarding">
<a class="leadership-home" href="/" aria-label="Return to home"><svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M12 2 2 11v11h7v-8h6v8h7V11L12 2Z"/></svg></a>
<main class="leadership-form">
<h1 data-leadership-title>Student Leadership Information</h1>
<section class="leadership-card">
@include('components.flash')
<form action="/apply/leadership" method="post" enctype="multipart/form-data" data-leadership-form>
@csrf
<div data-leadership-step="0">
<p class="leadership-required-note">Fields marked with an asterisk (<span class="leadership-required-mark">*</span>) are required.</p>
<label>Full name <span class="leadership-required-mark" aria-hidden="true">*</span><input name="name" value="{{ old('name',auth()->user()->profile_complete?auth()->user()->name:'') }}" placeholder="Enter your full name" autocomplete="name" maxlength="120" required></label>
<label>Student ID <span class="leadership-required-mark" aria-hidden="true">*</span><input name="student_id" value="{{ old('student_id',auth()->user()->student_id) }}" placeholder="Enter your Student ID" maxlength="40" required></label>
@php($leadershipCourses = \App\Models\User::whereNotNull('course')->where('course','<>','')->distinct()->orderBy('course')->pluck('course')->push(old('course',auth()->user()->course))->filter()->unique())
<label>Course <span class="leadership-required-mark" aria-hidden="true">*</span><select name="course" required><option value=""></option>@foreach($leadershipCourses as $course)<option value="{{ $course }}" @selected(old('course',auth()->user()->course)===$course)>{{ mb_strtoupper($course) }}</option>@endforeach</select></label>
<label>Year <span class="leadership-required-mark" aria-hidden="true">*</span><select name="year_level" required><option value=""></option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option @selected(old('year_level',auth()->user()->year_level)===$year)>{{ $year }}</option>@endforeach</select></label>
<label>Organization <span class="leadership-required-mark" aria-hidden="true">*</span><select name="details[organization_id]" required><option value=""></option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected(old('details.organization_id')==$organization->id)>{{ $organization->name }}</option>@endforeach</select></label>
<label>Position <span class="leadership-required-mark" aria-hidden="true">*</span><input name="details[position]" value="{{ old('details.position') }}" placeholder="Enter your position in organization" maxlength="100" required></label>
<button type="button" class="leadership-button" data-leadership-next>Next</button>
</div>
<div data-leadership-step="1" hidden>
<h2>Upload your following requirements</h2>
<p class="notice error" data-leadership-missing role="alert" hidden></p>
@foreach(['endorsement'=>'Endorsement letter','candidacy'=>'Certificate of Candidacy','student_card'=>'Scanned Copy of ID','registration'=>'Scanned Copy of Enrollment form'] as $key=>$label)
<div class="leadership-document-row">
<label for="leadership-document-{{ $key }}">{{ $label }}</label>
<span class="leadership-upload-control"><span>Upload</span><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="m8 13 6-6a3 3 0 0 1 4 4l-8 8a5 5 0 0 1-7-7l9-9a4 4 0 0 1 6 6l-9 9a2 2 0 0 1-3-3l8-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><input id="leadership-document-{{ $key }}" type="file" name="documents[{{ $key }}]" accept=".pdf,.jpg,.jpeg,.png" data-max-mb="5" aria-describedby="leadership-filename-{{ $key }}" required></span>
<span class="leadership-filename" id="leadership-filename-{{ $key }}" aria-live="polite"></span>
</div>
@endforeach
<button type="submit" class="leadership-button leadership-submit">Submit</button>
</div>
</form>
</section>
</main>
<script src="{{ asset('leadership-onboarding.js') }}?v=20261120" defer></script>
</body></html>
