@include('workspace.drafts',['draftKind'=>'assignment'])
@include('workspace.pending-community-service')
<details class="panel" @if($errors->hasAny(['Minor.*','Major.*'])) open @endif>
    <summary>Community service hour limits</summary>
    <p class="muted">Set the approved range for each violation type. Use the same minimum and maximum for a fixed number of hours.</p>
    @if(!$communityHourLimits)
        <p class="muted">The general 1–500 hour limit applies until you save these ranges.</p>
    @endif
    <form method="post" action="{{ route('staff.community.hour-limits') }}">
        @csrf
        <div class="form-grid">
            @foreach(['Minor','Major'] as $severity)
                @foreach(['min'=>'minimum','max'=>'maximum'] as $bound=>$label)
                    <label class="field">{{ $severity }} {{ $label }} hours
                        <input name="{{ $severity }}[{{ $bound }}]" type="number" min="1" max="500" required
                            value="{{ old($severity.'.'.$bound,$communityHourLimits[$severity][$bound]??'') }}">
                    </label>
                @endforeach
            @endforeach
        </div>
        <div class="actions"><button type="submit">Save Hour Limits</button></div>
    </form>
</details>
<div class="panel"><div class="toolbar"><h2>Community Service</h2><button data-dialog="assign-student">Add Violation</button></div><div class="table-wrap"><table><thead><tr><th>Student / Record</th><th>Department</th><th>Supervisor</th><th>Required Hours</th><th>Verified Hours</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($assignments as $a)<tr><td><strong>{{$a->user->name}}</strong><small>{{$a->user->student_id}} · {{$a->record_number}}</small><small>{{$a->offense}} · {{$a->severity}}</small></td><td>{{$a->department}}</td><td>{{$a->supervisor}}</td><td>{{$a->required_minutes/60}}</td><td>{{round($a->approvedMinutes()/60,2)}}</td><td><span class="status {{$a->status}}">{{ucfirst($a->status)}}</span></td><td><button class="table-action outline" data-dialog="assignment-{{$a->id}}">Review</button></td></tr>@empty<tr><td colspan="7" class="empty">No community service records.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel" id="attendance-verification"><h2>Attendance Verification</h2><div class="table-wrap"><table><thead><tr><th>Student</th><th>Time In</th><th>Time Out</th><th>Review</th></tr></thead><tbody>@forelse($attendance->whereNotNull('assignment_id') as $a)<tr><td>{{$a->user->name}}<small>{{$a->assignment->record_number}} · {{$a->assignment->offense}}</small></td>@include('workspace.attendance-time',['record'=>$a,'side'=>'in'])@include('workspace.attendance-time',['record'=>$a,'side'=>'out'])<td><form method="post" action="/staff/attendance/{{$a->id}}">@csrf<input aria-label="Attendance review note" name="review_note" minlength="5" placeholder="Verification note" required><button name="status" value="approved" class="table-action">Verify</button><button name="status" value="rejected" class="table-action outline">Return</button></form></td></tr>@empty<tr><td colspan="4" class="empty">No attendance awaiting verification.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel" id="duty-journal"><h2>Duty Journal</h2>@forelse($journals as $journal)<article class="summary-line"><div><strong>{{$journal->assignment->user->name}} · Module {{$journal->module}}</strong><small>{{$journal->assignment->record_number}} · {{$journal->assignment->offense}}</small><p class="text-body">{{$journal->answer}}</p></div><form method="post" action="/staff/journals/{{$journal->id}}">@csrf<button class="table-action" name="status" value="approved">Approve</button><button class="table-action outline" name="status" value="rejected">Return</button></form></article>@empty<p class="empty">No journals awaiting review.</p>@endforelse</div>
@push('dialogs')@foreach($assignments as $a)<dialog id="assignment-{{$a->id}}" class="drawer" @if(session('community_feedback_assignment') === $a->id) data-auto-open @endif><button class="close-dialog" data-close aria-label="Close">×</button><div class="panel"><h2>Selected Student Details</h2><h3>{{$a->user->name}}</h3><p class="muted">{{$a->user->student_id}} · {{$a->user->course}} · {{$a->record_number}}</p><dl class="details-list"><div><dt>Case</dt><dd>{{$a->offense}}</dd></div><div><dt>Deployment</dt><dd>{{$a->department}}</dd></div><div><dt>Supervisor</dt><dd>{{$a->supervisor}}</dd></div><div><dt>Required Hours</dt><dd>{{$a->required_minutes/60}}</dd></div></dl><h3>Progress</h3><div class="progress"><span style="width:{{min(100,$a->approvedMinutes()/max(1,$a->required_minutes)*100)}}%"></span></div><p>{{round($a->approvedMinutes()/60,2)}} / {{$a->required_minutes/60}} verified hours</p><h3>Student Submissions</h3>@foreach($a->journals as $j)<p>Module {{$j->module}} — {{$j->status}}</p>@endforeach<p class="muted">Verify required hours and all four journal modules first.</p>@if($a->status==='active')<form method="post" action="/staff/assignments/{{$a->id}}/complete">@csrf<button>Mark Service Completed</button></form><button class="outline" data-dialog="edit-assignment-{{$a->id}}">Edit Assignment</button>@endif
@include('workspace.service-feedback',['assignment'=>$a])
</div></dialog><dialog id="edit-assignment-{{$a->id}}"><button class="close-dialog" data-close aria-label="Close">×</button><form class="panel" method="post" action="/workspace/assignment/{{$a->id}}">@csrf<h2>Edit Assignment</h2>
<p class="muted">{{ $a->severity }} violation.
    @if($limit=$communityHourLimits[$a->severity]??null)
        Enter {{ $limit['min'] }} to {{ $limit['max'] }} hours when changing the requirement.
    @endif
</p><div class="form-grid"><label class="field">Deployment<input name="department" value="{{$a->department}}" required></label><label class="field">Supervisor<input name="supervisor" value="{{$a->supervisor}}" required></label><label class="field">Required Hours<input type="number" name="required_hours" value="{{$a->required_minutes/60}}" min="1" max="500" required></label><label class="field full">Note<textarea name="note">{{$a->note}}</textarea></label></div><div class="actions"><button>Save Changes</button></div></form></dialog>@endforeach
@endpush
