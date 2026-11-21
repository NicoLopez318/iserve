<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scholar Information · iServe</title><link rel="stylesheet" href="{{ asset('app.css') }}"><link rel="stylesheet" href="{{ asset('scholar-onboarding.css') }}"><link rel="stylesheet" href="{{ asset('responsive.css') }}?v=20261112"></head>
<body class="scholar-information-screen">
<a class="scholar-home" href="/" aria-label="Return to home"><svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M12 2 2 11v11h7v-8h6v8h7V11L12 2Z"/></svg></a>
<main class="scholar-information"><h1>Scholar Information</h1><section class="scholar-card"><h2>Details</h2>@include('components.flash')
<form method="post" action="/scholar/information">@csrf
<label>Full name<input name="name" value="{{ old('name',auth()->user()->profile_complete?auth()->user()->name:'') }}" placeholder="Enter your full name" autocomplete="name" maxlength="120" required></label>
<label>Student ID<input name="student_id" value="{{ old('student_id',auth()->user()->student_id) }}" placeholder="Enter your student ID" maxlength="40" required></label>
<label>Course<select name="course" required><option value=""></option>@foreach($courses as $course)<option value="{{ $course }}" @selected(old('course',auth()->user()->course)===$course)>{{ mb_strtoupper($course) }}</option>@endforeach</select></label>
<label>Year<select name="year_level" required><option value=""></option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level',auth()->user()->year_level)===$year)>{{ $year }}</option>@endforeach</select></label>
<button class="scholar-next" type="submit">Next</button>
</form></section></main>
</body></html>