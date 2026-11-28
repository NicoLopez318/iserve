<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Re-Admission Virtual Slip · {{$item->ticket}}</title>
<style>
@font-face{font-family:Montserrat;src:url('/fonts/montserrat.woff2');font-weight:100 900}*{box-sizing:border-box}body{margin:0;background:#fff5f6;color:#111;font-family:Montserrat,Arial,sans-serif}.virtual-slip{max-width:760px;margin:24px auto;padding:28px 32px}h1{font-size:29px;margin:0 0 24px;font-weight:700}.slip-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 40px;margin:0;padding:0 10px}dt{font-size:18px;color:#858080;margin-bottom:5px}dd{font-size:18px;margin:0;overflow-wrap:anywhere;line-height:1.4}.slip-decision{border-top:1px solid #d5d0d1;border-bottom:1px solid #d5d0d1;margin:22px 0 18px;padding:16px 10px 22px;display:grid;gap:16px}.slip-decision .approved{color:#00b518}.slip-note{background:#e1d7d9;color:#aa9a9e;border-radius:10px;margin:34px 0;padding:18px 26px;font-size:13px;font-weight:600;line-height:1.7}.slip-actions{text-align:center;margin-top:30px}button{background:#ff000d;color:white;border:0;border-radius:10px;padding:18px 32px;font:700 18px Montserrat,Arial;cursor:pointer}.notice{padding:12px 16px;border:1px solid #95cc9b;background:#eefbef;border-radius:8px;margin-bottom:18px;font-size:14px}.slip-date{font-variant-numeric:tabular-nums}@media(max-width:600px){.virtual-slip{margin:0 auto;padding:24px 20px}h1{font-size:26px}.slip-grid{gap:18px 24px}dt,dd{font-size:16px}.slip-note{padding:16px 22px}}@media print{body{background:white}.virtual-slip{max-width:none;margin:0}.slip-actions,.notice{display:none}.slip-note{border:1px solid #ddd}.slip-decision .approved{color:#111}}
</style></head><body><main class="virtual-slip">
@if(session('success'))<p class="notice" role="status">{{session('success')}}</p>@endif
<h1>Re-Admission Virtual Slip</h1>
@php
    $applicant = $item->details['applicant'] ?? [];
    $interviewDate = strtotime($item->details['interview_at'] ?? '');
    $issuedDate = strtotime($item->details['issued_at'] ?? $item->details['slip_issued_at'] ?? '');
@endphp
<dl class="slip-grid">
<div><dt>Slip Number</dt><dd>{{$item->ticket}}</dd></div><div><dt>Student ID</dt><dd>{{$applicant['student_id']??$item->user->student_id??'—'}}</dd></div>
<div><dt>Student Name</dt><dd>{{$applicant['name']??$item->user->name}}</dd></div><div><dt>Course</dt><dd>{{strtoupper($applicant['course']??$item->user->course??'—')}}</dd></div>
<div><dt>Year</dt><dd>{{$applicant['year_level']??$item->user->year_level??'—'}}</dd></div><div><dt>Instructor</dt><dd>{{$item->details['instructor']??'—'}}</dd></div>
@if(filled($item->details['subject']??null))
<div><dt>Subject</dt><dd>{{$item->details['subject']}}</dd></div>
@endif
@if(filled($item->details['subject_code']??null))
<div><dt>Subject Code</dt><dd>{{$item->details['subject_code']}}</dd></div>
@endif
</dl>
<dl class="slip-decision">
<div><dt>Decision</dt><dd class="approved">Approved for Admission</dd></div>
<div><dt>Interview Date</dt><dd class="slip-date">{{ $interviewDate !== false ? date('M j, Y', $interviewDate) : '—' }}</dd></div>
<div><dt>Remarks</dt><dd>{{$item->review_note?:'Student may return to class and present this virtual slip to the instructor.'}}</dd></div>
</dl>
<dl class="slip-grid"><div><dt>Issued By</dt><dd>{{ $item->details['issued_by'] ?? $item->reviewer?->name ?? 'CSLD' }}</dd></div><div><dt>Date Issued</dt><dd class="slip-date">{{ $issuedDate !== false ? date('M j, Y', $issuedDate) : ($item->reviewed_at?->format('M j, Y') ?? '—') }}</dd></div></dl>
<p class="slip-note">This is a system-generated readmission slip<br>please present this slip to your instructor upon return to class.</p>
<div class="slip-actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
</main></body></html>
