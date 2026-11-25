@extends('workspace.layout')
@section('content')
@php($role=auth()->user()->role)
@php($monitor=in_array($role,['admin','assistant']))
@if($role === 'sfu_head' && $page === 'dashboard')
 @include('workspace.sfu-dashboard')
@elseif($role === 'sfu_head' && $page === 'applications')
 @include('workspace.unit-stat-cards')
 @include('workspace.sfu-application-queue', ['fullQueue' => true])
 @include('workspace.drafts',['draftKind'=>'scholar'])
 @if($selectedRequest)
 <dialog id="transaction-review" class="sfu-review-dialog" open data-auto-open aria-labelledby="sfu-review-title">
     <a class="close-dialog" href="{{ request()->fullUrlWithoutQuery(['review','report']) }}" aria-label="Close application review">×</a>
     @include('workspace.sfu-review-panel')
 </dialog>
 @endif
@elseif($role === 'sfu_head' && $page === 'reports')
 @include('workspace.sfu-reports')
@elseif($role === 'slu_head' && $page === 'reports')
 @include('workspace.slu-reports')
@elseif(in_array($role, ['sfu_head', 'slu_head'], true) && $page === 'settings')
 @include('workspace.unit-settings')
@elseif($role === 'slu_head' && $page === 'dashboard')
 @include('workspace.slu-dashboard')
@elseif($role === 'slu_head' && $page === 'proposals')
 @include('workspace.slu-proposals')
@elseif($role === 'slu_head' && $page === 'achievements')
 @include('workspace.slu-achievements')
@else
@if(count($design['cards']??[]))
 <section class="cards" aria-label="Summary" style="grid-template-columns:repeat({{count($design['cards'])}},minmax(0,1fr))">@foreach($design['cards'] as $card)<div class="card">@if($card['icon']&&is_file(public_path('staff-assets/'.$card['icon'])))<img src="/staff-assets/{{$card['icon']}}" alt="">@endif<div><strong>{{$card['value']}}</strong><span>{{$card['label']}}</span></div></div>@endforeach</section>
@endif
@if($page==='dashboard')
 @if($monitor)@include('workspace.report-list')
 <div class="columns"><div class="panel"><h2>Unit Review</h2><div class="table-wrap"><table><thead><tr><th>Unit</th><th>Pending</th><th>Admin Review</th><th>Acknowledged</th><th>Total</th></tr></thead><tbody>@foreach(['slu_head'=>'SLU','sfu_head'=>'SFU','cvu_head'=>'CVU'] as $unit=>$label)@php($unitReports=App\Models\Report::whereHas('user',fn($q)=>$q->where('role',$unit))->get())<tr><td>{{$label}}</td><td>{{$unitReports->where('status','pending')->count()}}</td><td>{{$unitReports->where('status','awaiting_admin')->count()}}</td><td>{{$unitReports->where('status','approved')->count()}}</td><td>{{$unitReports->count()}}</td></tr>@endforeach</tbody></table></div></div><div class="panel"><h2>Recent Transactions</h2>@forelse($audit->take(5) as $entry)<div class="summary-line"><span>{{ucwords(str_replace(['.','_'], ' ',$entry->action))}}<small class="muted"> · {{$entry->subject}}</small></span><small>{{\Carbon\Carbon::parse($entry->created_at)->format('M j, g:i A')}}</small></div>@empty<p class="empty">No transaction activity.</p>@endforelse</div><div class="panel full"><h2>Upcoming Deadlines</h2><p class="muted">{{$reports->whereIn('status',['pending','awaiting_admin'])->count()}} reports awaiting review</p></div></div>
 @else
 @include('workspace.requests',['heading'=>$role==='sfu_head'?'Applications':($role==='slu_head'?'Proposal Queue':'Recent Transactions')])
 <div class="columns"><div class="panel"><h2>{{$role==='sfu_head'?'Semester Summary':'Today’s Summary'}}</h2>@foreach($role==='sfu_head'?['scholarship'=>'Tuition Fee Discount']:config('portal.office.'.$role,[]) as $key=>$value)@php($kind=is_string($key)?$key:$value)<div class="summary-line"><span>{{App\Models\ServiceRequest::kinds()[$kind]??$kind}}</span><strong class="summary-number">{{App\Models\ServiceRequest::where('kind',$kind)->whereDate('created_at',today())->count()}}</strong></div>@endforeach</div><div class="panel"><h2>{{$role==='cvu_head'?'Community Service Overview':($role==='sfu_head'?'Semester Report':'Events & Activities')}}</h2>@if($role==='cvu_head')<div class="summary-line">Active Students <strong>{{$assignments->where('status','active')->count()}}</strong></div><div class="summary-line">For Verification <strong>{{$attendance->whereNotNull('assignment_id')->count()}}</strong></div><a class="button outline" href="/workspace/community">View Community Service</a>@elseif($role==='sfu_head')<div class="summary-line">Under Assistant Review <strong>{{$reportCounts['pending']??0}}</strong></div><a class="button outline" href="/workspace/reports">Prepare Semester Report</a>@else @forelse($events->take(4) as $event)<div class="summary-line"><span>{{$event->title}}</span><small>{{$event->starts_at->format('M j')}}</small></div>@empty<p class="empty">No events found.</p>@endforelse<a class="button outline" href="/workspace/events">View Events</a>@endif</div></div>
 @endif
@elseif(in_array($page,['complaints','lost-found','readmission','id-replacement']))
 @include('workspace.requests',['heading'=>$menus[$page]])
@elseif(in_array($page,['inbox','leadership','scholarship','values']))
 @include('workspace.report-list')@include('workspace.requests',['heading'=>'User Transactions'])
@elseif($page==='reports')
 @include('workspace.report-list',['reportHeading'=>'Reports'])
 <div class="panel"><h2>Report Preview</h2><p class="muted">Compile activity for a selected period. Submission preserves a snapshot and version history.</p><div class="table-wrap"><table><thead><tr><th>Service</th><th>Approved</th><th>Pending at Period End</th></tr></thead><tbody>@foreach(config('portal.office.'.$role,[]) as $kind)<tr><td>{{App\Models\ServiceRequest::kinds()[$kind]}}</td><td>{{App\Models\ServiceRequest::where('kind',$kind)->where('status','approved')->count()}}</td><td>{{App\Models\ServiceRequest::where('kind',$kind)->whereIn('status',['pending','in_review','unit_approved','awaiting_admin'])->count()}}</td></tr>@endforeach</tbody></table></div></div>
@elseif($page==='history')
 <div class="panel"><h2>{{$role==='admin'?'Decision History':'Submission History'}}</h2><div class="table-wrap"><table><thead><tr><th>Report / Transaction</th><th>Action</th><th>Action Date</th><th>Feedback</th></tr></thead><tbody>@forelse($audit as $entry)<tr><td>{{$entry->subject}}</td><td>{{ucwords(str_replace(['.','_'],' ',$entry->action))}}</td><td>{{\Carbon\Carbon::parse($entry->created_at)->format('M j, Y g:i A')}}</td><td>{{$entry->details}}</td></tr>@empty<tr><td colspan="4" class="empty">No review history.</td></tr>@endforelse</tbody></table></div></div>
@elseif($page==='community')
 @include('workspace.community')
@elseif(in_array($page,['events','seminars']))
 @include('workspace.events')
@elseif($page==='clearance')
 @include('workspace.requests',['heading'=>'Appointment Requests'])
 <div class="panel"><div class="toolbar"><h2>Availability & Slots</h2><button data-dialog="availability">Add Available Date</button></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Time</th><th>Capacity</th><th>Booked</th><th>Status</th></tr></thead><tbody>@forelse($slots as $slot)<tr><td>{{$slot['date']}}</td><td>{{$slot['start']}} – {{$slot['end']}}</td><td>{{$slot['capacity']}}</td><td>{{App\Models\ServiceRequest::where('kind','clearance')->where('details->appointment_date',$slot['date'])->where('details->schedule',$slot['start'])->whereNotIn('status',['cancelled','rejected'])->count()}}</td><td>{{$slot['open']??false?'Open for booking':'Closed'}}</td></tr>@empty<tr><td colspan="5" class="empty">No office availability configured.</td></tr>@endforelse</tbody></table></div></div>
@elseif($page==='organizations')
 @include('workspace.organizations')
@elseif($page==='points')
 @include('workspace.points')
@elseif($page==='content')
 @include('workspace.content')
@elseif($page==='settings')
 @include('workspace.settings')
@endif
@endif
@endsection
@push('dialogs')@include('workspace.dialogs')
@if($selectedRequest&&!($role==='sfu_head'&&in_array($page,['dashboard','applications'])))
@php($adminView = $role === 'admin' && !$selectedRequest->reviewActions(auth()->user()))
@php($scholarshipReview = $role === 'admin' && $page === 'scholarship' && $selectedRequest->kind === 'scholarship')
@php($leadershipReview = in_array($role, ['admin', 'slu_head'], true) && ($page === 'proposals' || in_array($selectedRequest->kind, ['activity', 'leadership'], true)))
@php($membershipReview = $membershipTab && $selectedRequest->kind === 'leadership')
@php($sluProposalReview = $role === 'slu_head' && $page === 'proposals' && $selectedRequest->kind === 'activity' && empty($selectedRequest->details['completion_report']))
@php($sluAchievementReview = $role === 'slu_head' && $page === 'achievements' && $selectedRequest->kind === 'achievement' && empty($selectedRequest->details['adjustment']))
@php($centerReview = $adminView || $scholarshipReview || $leadershipReview || $sluAchievementReview)
@php($closeReviewUrl=$role==='slu_head'&&$page==='organizations'?request()->fullUrlWithQuery(['tab'=>'membership','review'=>null,'report'=>null]):request()->fullUrlWithoutQuery(['review','report']))
<dialog id="transaction-review" @class(['drawer review-drawer' => !$centerReview, 'slu-membership-dialog' => $membershipReview, 'slu-proposal-dialog' => $sluProposalReview, 'slu-achievement-dialog' => $sluAchievementReview])
        open data-auto-open
        @if($membershipReview) aria-labelledby="slu-membership-review-title"
        @elseif($sluProposalReview) aria-labelledby="slu-proposal-review-title"
        @elseif($sluAchievementReview) aria-labelledby="slu-achievement-review-title"
        @else aria-label="Transaction details" @endif>
    <a class="close-dialog" href="{{ $closeReviewUrl }}" aria-label="Close">×</a>
    @include('workspace.review-panel',['item'=>$selectedRequest,'history'=>$selectedHistory])
</dialog>
@endif
@if($selectedReport)
@php($sluReportReview = $role === 'slu_head' && $page === 'reports')
<dialog id="report-review" @class(['slu-report-dialog'=>$sluReportReview,'drawer review-drawer'=>!$sluReportReview && !($role==='admin' && $selectedReport->status==='approved')])
        open data-auto-open @if($sluReportReview) aria-labelledby="slu-report-review-title" @else aria-label="Report details" @endif>
    <a class="close-dialog" href="{{ request()->fullUrlWithoutQuery(['review','report']) }}" aria-label="Close">×</a>
    @include($sluReportReview ? 'workspace.slu-report-review' : 'workspace.report-review-panel',['item'=>$selectedReport,'history'=>$reportHistory])
</dialog>
@endif
@endpush
