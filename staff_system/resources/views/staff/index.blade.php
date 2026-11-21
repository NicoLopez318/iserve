@php($portal='values')@php($page='staff')
@extends('layouts.portal')
@section('subtitle','Review submissions, verify participation, and manage university services.')
@section('content')
<div class="section-heading"><div><span class="eyebrow">{{ str_replace('_',' ',auth()->user()->role) }}</span><h2>Staff Workspace</h2></div><a class="btn primary" href="/staff/export">Export Requests ↓</a></div><div class="stats-grid">
@foreach(['pending'=>'Pending','in_review'=>'In Review','unit_approved'=>'Awaiting Assistant','awaiting_admin'=>'Awaiting Admin','approved'=>'Approved','needs_revision'=>'Needs Revision'] as $key=>$label)<a class="panel" href="/staff?status={{ $key }}"><small>{{ $label }}</small><h2>{{ $counts[$key]??0 }}</h2></a>
@endforeach</div>
<nav class="workspace-tabs" aria-label="Workspace sections"><button class="active" data-tab="requests">Requests</button>@if(auth()->user()->role!=='assistant')<button data-tab="announcements">Announcements</button>@endif
@if(in_array(auth()->user()->role,['admin','cvu_head','slu_head']))<button data-tab="verification">Verification</button>
@endif 
@if(in_array(auth()->user()->role,['admin','cvu_head']))<button data-tab="community">Community Records</button>
@endif 
@if(in_array(auth()->user()->role,['admin','slu_head']))<button data-tab="events">Events & Seminars</button>
@endif 
@if(auth()->user()->role==='assistant')<button data-tab="audit">Audit History</button>@endif
@if(auth()->user()->role==='admin')<button data-tab="users">Staff Accounts</button><button data-tab="audit">Audit History</button><button data-tab="settings">System Settings</button>
@endif</nav>
<section data-tab-panel="requests"><section class="panel"><h2>Applications & Transactions</h2><form class="filter-form" action="/staff"><label>Search<input name="search" value="{{ request('search') }}" placeholder="Ticket or applicant"></label><label>Status<select name="status"><option value="">All statuses</option>
@foreach(['pending','in_review','unit_approved','awaiting_admin','needs_revision','approved','rejected','cancelled'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>
@endforeach</select></label><label>Type<select name="kind"><option value="">All types</option>
@foreach(App\Models\ServiceRequest::kinds() as $k=>$l)<option value="{{ $k }}" @selected(request('kind')===$k)>{{ $l }}</option>
@endforeach</select></label><button class="btn primary">Filter</button><a href="/staff">Reset</a></form>
<div class="table-wrap"><table><thead><tr><th>Ticket</th><th>Applicant</th><th>Type</th><th>Status</th><th>Submitted</th><th>Last reviewer</th><th></th></tr></thead><tbody>
@forelse($requests as $q)<tr><td>{{ $q->ticket }}</td><td>{{ $q->user->name }}</td><td>{{ App\Models\ServiceRequest::kinds()[$q->kind] }}</td><td><span class="badge {{ $q->status }}">{{ str_replace('_',' ',$q->status) }}</span></td><td>{{ $q->created_at->format('M j, Y') }}</td><td>{{ $q->reviewer?->name??'—' }}<small>{{ str_replace('_',' ',$q->reviewer?->role??'') }}</small></td><td><a href="/requests/{{ $q->id }}">Review →</a></td></tr>
@empty<tr><td colspan="7" class="empty">No matching requests.</td></tr>
@endforelse</tbody></table></div><div class="pagination-links">
@if($requests->previousPageUrl())<a href="{{ $requests->previousPageUrl() }}">← Previous</a>
@endif<span>Page {{ $requests->currentPage() }} of {{ $requests->lastPage() }}</span>
@if($requests->nextPageUrl())<a href="{{ $requests->nextPageUrl() }}">Next →</a>
@endif</div></section></section>
<section data-tab-panel="verification" hidden><section class="panel"><h2>Attendance Verification</h2>
@forelse($attendance as $a)<article class="review-card"><div class="section-heading"><h3>{{ $a->user->name }}</h3><small>{{ $a->event?->title??$a->assignment?->record_number }}</small></div><p>{{ $a->time_in?->format('M j · g:i A') }} → {{ $a->time_out?->format('g:i A') }} · {{ number_format($a->minutes/60,1) }} hours</p>
@if($a->reflection)<p class="pre-wrap">{{ $a->reflection }}</p>
@endif 
@foreach(['in','out'] as $side)
@if($a->{'photo_'.$side})<a class="file-link" href="/staff/attendance/{{ $a->id }}/evidence/{{ $side }}">Download time {{ $side }} evidence</a>
@endif 
@endforeach<form method="post" action="/staff/attendance/{{ $a->id }}">@csrf<div class="form-grid"><label>Decision<select name="status"><option value="approved">Approve</option><option value="rejected">Reject</option></select></label><label>Staff note<input name="review_note" required minlength="5"></label></div><button class="btn primary">Verify Attendance</button></form></article>
@empty<p class="empty">No attendance awaiting verification.</p>
@endforelse</section><section class="panel"><h2>Duty Journal Reviews</h2>
@forelse($journals as $j)<article class="review-card"><h3>{{ $j->assignment->user->name }} · Module {{ $j->module }}</h3><p class="pre-wrap">{{ $j->answer }}</p><form class="inline-form" method="post" action="/staff/journals/{{ $j->id }}">@csrf<select name="status" aria-label="Journal decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select><button class="btn primary">Save Decision</button></form></article>
@empty<p class="empty">No journals awaiting review.</p>
@endforelse</section></section>
<section data-tab-panel="community" hidden><section class="panel"><h2>Assign Community Service</h2><form method="post" action="/staff/assignments">@csrf<div class="form-grid"><label>Student<select name="user_id" required><option value="">Choose student</option>
@foreach($users->where('account_type','student') as $student)<option value="{{ $student->id }}">{{ $student->name }} · {{ $student->student_id }}</option>
@endforeach</select></label><label>Offense<input name="offense" required></label><label>Severity<select name="severity"><option>Minor</option><option>Major</option></select></label><label>Required Hours<input type="number" name="required_hours" min="1" max="500" required></label><label>Department<input name="department" required></label><label>Supervisor<input name="supervisor" required></label><label class="span-2">Instructions<textarea name="note" rows="3"></textarea></label></div><button class="btn primary">Create Record</button></form></section><section class="panel"><h2>Community Records</h2>
@foreach($assignments as $a)<article class="review-card"><div class="section-heading"><h3>{{ $a->user->name }} · {{ $a->record_number }}</h3><span class="badge {{ $a->status }}">{{ $a->status }}</span></div><p>{{ $a->offense }} · {{ number_format($a->approvedMinutes()/60,1) }}/{{ $a->required_minutes/60 }} verified hours · {{ $a->journals()->where('status','approved')->count() }}/4 journals approved</p>
@if($a->status==='active')<form method="post" action="/staff/assignments/{{ $a->id }}/complete" data-confirm="Complete this community record after final verification?">@csrf<button class="btn green">Final Verification & Complete</button></form>
@endif</article>
@endforeach</section></section>
<section data-tab-panel="events" hidden><section class="panel"><h2>Publish Event / Seminar</h2><form method="post" action="/staff/events">@csrf<div class="form-grid"><label>Title<input name="title" required></label><label>Type<select name="type"><option value="leadership">Leadership Activity</option><option value="seminar">Seminar</option></select></label><label class="span-2">Description<textarea name="description" rows="3" required></textarea></label><label>Venue<input name="venue" required></label><label>Capacity<input name="capacity" type="number" min="1" max="5000" value="100" required></label><label>Starts At<input name="starts_at" type="datetime-local" required></label><label>Ends At<input name="ends_at" type="datetime-local" required></label><label>EXP Points<input name="points" type="number" min="0" max="1000" value="50" required></label></div><button class="btn orange">Publish Event</button></form></section><section class="panel"><h2>Published Events</h2>
@foreach($events as $event)<article class="review-card"><h3>{{ $event->title }}</h3><p>{{ $event->starts_at->format('M j, Y · g:i A') }} · {{ $event->venue }} · {{ $event->status }}</p>
@if($event->status==='published')<form method="post" action="/staff/events/{{ $event->id }}/cancel" data-confirm="Cancel this event?">@csrf<button class="btn red">Cancel Event</button></form>
@endif</article>
@endforeach</section></section>
@if(auth()->user()->role==='admin')<section data-tab-panel="users" hidden><section class="panel"><h2>Create Staff Account</h2><form method="post" action="/staff/users">@csrf<div class="form-grid"><label>Full Name<input name="name" required></label><label>Email<input name="email" type="email" required></label><label>Username<input name="username" required></label><label>Role<select name="role">
@foreach(['sfu_head'=>'Scholarship Head','cvu_head'=>'Community Values Head','slu_head'=>'Leadership Head','assistant'=>'Assistant','admin'=>'Administrator'] as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>
@endforeach</select></label><label>Initial Password<input name="password" type="password" minlength="12" required autocomplete="new-password"></label></div><button class="btn primary">Create Account</button></form></section><section class="panel"><h2>Staff Accounts</h2>
@foreach($users->filter(fn($u)=>$u->isStaff()) as $staff)<div class="record-row"><strong>{{ $staff->name }}</strong><span>{{ $staff->email }} · {{ $staff->role }}</span></div>
@endforeach</section></section>
@endif
@if(in_array(auth()->user()->role,['admin','assistant']))
<section data-tab-panel="audit" hidden><section class="panel"><h2>Audit History</h2><div class="table-wrap"><table><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Subject</th><th>Details</th></tr></thead><tbody>
@foreach($audit as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $users->firstWhere('id',$log->user_id)?->name??'System' }}</td><td>{{ $log->action }}</td><td>{{ $log->subject }}</td><td>{{ $log->details }}</td></tr>
@endforeach</tbody></table></div></section></section>
@endif
@if(auth()->user()->role==='admin')
@php($settings=Illuminate\Support\Facades\DB::table('settings')->pluck('value','key'))
<section data-tab-panel="settings" hidden><section class="panel"><h2>Fee Schedule & Academic Term</h2><form method="post" action="/staff/settings">@csrf<div class="form-grid"><label>Tuition Fee (PHP)<input type="number" name="tuition" min="0" step="0.01" value="{{ ($settings['tuition']??2000000)/100 }}" required></label><label>Laboratory Fee (PHP)<input type="number" name="laboratory" min="0" step="0.01" value="{{ ($settings['laboratory']??500000)/100 }}" required></label><label>Miscellaneous Fee (PHP)<input type="number" name="miscellaneous" min="0" step="0.01" value="{{ ($settings['miscellaneous']??350000)/100 }}" required></label><label>Academic Year<input name="academic_year" value="{{ $settings['academic_year']??'2026–2027' }}" required></label><label>Semester<select name="semester">
@foreach(['1st Semester','2nd Semester','Summer'] as $s)<option @selected(($settings['semester']??'1st Semester')===$s)>{{ $s }}</option>
@endforeach</select></label></div><button class="btn primary">Save Settings</button></form><p class="muted">All scholarship discounts use this fee schedule. Review Finance Office values before publishing official tuition amounts.</p></section></section>
@endif
<section data-tab-panel="announcements" hidden><section class="panel"><h2>CSLD Announcements</h2><form method="post" action="/staff/announcements">@csrf<div class="form-grid"><label>Title<input name="title" maxlength="180" required></label><label>Unit<select name="unit">
@foreach(App\Http\Controllers\PublicController::units() as $key=>$unit)
@if(auth()->user()->role==='admin'||auth()->user()->role===['leadership'=>'slu_head','scholarship'=>'sfu_head','values'=>'cvu_head'][$key])<option value="{{ $key }}">{{ $unit['name'] }}</option>
@endif
@endforeach</select></label><label class="span-2">Notice<textarea name="body" rows="6" maxlength="10000" required></textarea></label></div><label class="check"><input type="checkbox" name="publish" value="1">Publish on the public Announcements page</label><button class="btn primary">Save Announcement</button><a class="btn secondary" href="/announcements">View Public Announcements</a></form></section></section>@endsection


