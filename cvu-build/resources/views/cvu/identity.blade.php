<label>Full name<input name="name" maxlength="120" value="{{ old('name',$u->name) }}" required></label>
<label>Student ID<input name="student_id" maxlength="40" value="{{ old('student_id',$u->student_id) }}" required></label>
<label>Contact<input name="contact" maxlength="40" value="{{ old('contact',$u->phone) }}" required></label>
<label>Course<input name="course" list="cvu-courses" maxlength="100" value="{{ old('course',$u->course) }}" placeholder="Enter your course" required></label>
<datalist id="cvu-courses">@foreach($courses as $course)<option value="{{ $course }}"></option>@endforeach</datalist>
<label>Year<select name="year_level" required><option value="">Select year</option>@foreach(App\Http\Controllers\CvuFlowController::YEARS as $year)<option @selected(old('year_level',$u->year_level)===$year)>{{ $year }}</option>@endforeach</select></label>
