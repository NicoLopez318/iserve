@extends('layouts.blue')
@section('title','Profile Setup · iServe')
@section('content')
<div class="center-page"><h1>Profile Setup</h1><section class="blue-card">@include('components.flash')<form action="/profile" method="post" enctype="multipart/form-data">@csrf
<label>Account Type<select name="account_type" id="account-type">@foreach(['student'=>'Student','employee'=>'Employee','guardian'=>'Guardian'] as $v=>$l)<option value="{{ $v }}" @selected(old('account_type',auth()->user()->account_type)===$v)>{{ $l }}</option>@endforeach</select></label>
<label>Full Name<input name="name" value="{{ old('name',auth()->user()->name) }}" required></label>
<div data-account="student">
    <label>Student ID<input name="student_id" value="{{ old('student_id',auth()->user()->student_id) }}"></label>
    <label>Course
        <select name="course" id="profile-course" required>
            <option value="">Select course</option>
            @foreach($courses as $course)
                <option value="{{ $course }}" @selected($selectedCourse === $course)>{{ mb_strtoupper($course) }}</option>
            @endforeach
            <option value="__other__" @selected($selectedCourse === '__other__')>Other</option>
        </select>
    </label>
    <div id="profile-other-course" @if($selectedCourse !== '__other__') hidden @endif>
        <label>Course name<input name="other_course" value="{{ old('other_course') }}" maxlength="100" @disabled($selectedCourse !== '__other__')></label>
    </div>
    <label>Year Level<select name="year_level"><option value="">Select year level</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option @selected(old('year_level',auth()->user()->year_level)===$year)>{{ $year }}</option>@endforeach</select></label>
</div>
<div data-account="employee"><label>Employee ID<input name="employee_id" value="{{ old('employee_id',auth()->user()->employee_id) }}"></label><label>College / Office<input name="office" value="{{ old('office',auth()->user()->office) }}"></label></div>
<div data-account="guardian"><label>Student Full Name<input name="ward_name" value="{{ old('ward_name',auth()->user()->ward_name) }}"></label><label>Student ID<input name="ward_student_id" value="{{ old('ward_student_id',auth()->user()->ward_student_id) }}"></label></div><label>Contact Number <small>(optional)</small><input name="phone" value="{{ old('phone',auth()->user()->phone) }}"></label><label>Profile Photo <small>(optional)</small><input type="file" name="avatar" accept="image/jpeg,image/png"></label><button class="btn orange full">Save and Continue</button></form></section></div>
@endsection


