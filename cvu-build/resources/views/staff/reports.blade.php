@php($portal='scholar')
@php($page='staff')
@extends('layouts.portal')
@section('content')
<div class="section-heading"><h2>Scholarship Semester Reports</h2><a href="/staff">Back to Workspace</a></div>
@if(auth()->user()->role==='sfu_head')<section class="panel"><h3>Submit a Semester Report</h3><form method="post" action="/staff/reports">@csrf<div class="form-grid"><label>Report Title<input name="title" required></label><label>Academic Year<input name="academic_year" required placeholder="2026–2027"></label><label>Semester<select name="semester"><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select></label><label class="span-2">Summary<textarea name="summary" rows="4" minlength="30" required></textarea></label></div><p class="muted">The report freezes a snapshot of approved scholarship awards for the selected term.</p><button class="btn primary">Submit to Assistant</button></form></section>@endif
@forelse($reports as $report)<section class="panel"><div class="section-heading"><h2>{{ $report->title }}</h2><span class="badge {{ $report->status }}">{{ $report->status }}</span></div><p>{{ $report->academic_year }} · {{ $report->semester }} · Submitted by {{ $report->user->name }}</p><p class="pre-wrap">{{ $report->summary }}</p><div class="table-wrap"><table><thead><tr><th>Ticket</th><th>Student</th><th>Student ID</th><th>Discount</th></tr></thead><tbody>@forelse($report->snapshot['records']??$report->snapshot as $row)<tr><td>{{ $row['ticket'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['student_id'] }}</td><td>{{ $row['rate']??0 }}%</td></tr>@empty<tr><td colspan="4">No approved awards for this term.</td></tr>@endforelse</tbody></table></div>@if($report->review_note)<div class="notice">{{ $report->review_note }}</div>@endif
@if((auth()->user()->role==='assistant'&&$report->status==='pending')||(auth()->user()->role==='admin'&&$report->status==='awaiting_admin'))
<form method="post" action="/staff/reports/{{ $report->id }}">@csrf<label>Decision<select name="status">
@if(auth()->user()->role==='assistant')
<option value="awaiting_admin">Forward to admin</option>
@else
<option value="approved">Final approval</option><option value="rejected">Reject</option>
@endif
<option value="needs_revision">Return for revision</option></select></label><label>Review Note<textarea name="review_note" rows="3" minlength="5" required></textarea></label><button class="btn primary">Save Decision</button></form>
@endif
@if(auth()->user()->role==='sfu_head'&&$report->user_id===auth()->id()&&$report->status==='needs_revision')
<form method="post" action="/staff/reports/{{ $report->id }}/revise">@csrf<label>Revised Summary<textarea name="summary" rows="4" minlength="30" required>{{ $report->summary }}</textarea></label><button class="btn primary">Resubmit to Assistant</button></form>
@endif
</section>@empty<section class="panel empty">No semester reports submitted yet.</section>@endforelse
@endsection
