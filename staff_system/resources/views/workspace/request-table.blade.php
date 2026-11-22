@php
 $columns=match($page??''){
  'complaints'=>['ticket'=>'Reference No.','name'=>'Complainant','respondent'=>'Violator','complaint_type'=>'Type','date'=>'Date Submitted','status'=>'Status'],
  'lost-found'=>['ticket'=>'Reference No.','name'=>'Reporter / Finder','kind'=>'Type','date'=>'Date Submitted','status'=>'Status'],
  'readmission'=>['ticket'=>'Reference No.','name'=>'Student','subject'=>'Subject','date'=>'Date Submitted','status'=>'Status'],
  'id-replacement'=>['ticket'=>'Reference No.','name'=>'Student','replacement_reason'=>'Type','course'=>'Course','date'=>'Date Submitted','status'=>'Status'],
  'applications'=>['ticket'=>'Application No.','name'=>'Applicant','course'=>'Course','requirements'=>'Requirements','status'=>'Status','date'=>'Submitted'],
  'proposals'=>['title'=>'Proposal','organization'=>'Organization','event_date'=>'Event Date','requirements'=>'Requirements','status'=>'Status','date'=>'Submitted'],
  'achievements'=>['title'=>'Achievement','organization'=>'Organization','achievement_date'=>'Achieved Date','awarded_points'=>'Suggest Point','evidence'=>'Evidence','status'=>'Status'],
  'clearance'=>['name'=>'Student','ticket'=>'Ticket','schedule'=>'Time','appointment_date'=>'Appointment','status'=>'Status'],
  'points'=>['title'=>'Event / Activity','organization'=>'Organization','kind'=>'Type','awarded_points'=>'Points','date'=>'Date','status'=>'Status'],
  default=>['ticket'=>'Reference No.','name'=>'Student','kind'=>'Transaction','date'=>'Date Submitted','status'=>'Status'],
 };
 if(($page??'')==='dashboard'&&auth()->user()->role==='sfu_head')$columns=['ticket'=>'Application No.','name'=>'Applicant','course'=>'Program','requirements'=>'Requirements','status'=>'Status','date'=>'Submitted'];
@endphp
<div class="panel"><h2>@include('workspace.icon',['name'=>'inbox']) {{ $heading??'Transactions' }}</h2>
<form class="toolbar" method="get"><div class="filters"><input name="search" aria-label="Search transactions" placeholder="Search reference no, name, or type...." value="{{request('search')}}"><select name="status" aria-label="Transaction status"><option value="">All Status</option>@foreach(['pending'=>'For Review','in_review'=>'Under Unit Review','needs_revision'=>'For Compliance','unit_approved'=>'Unit Approved','awaiting_admin'=>'Under Admin Review','approved'=>'Approved','rejected'=>'Declined','cancelled'=>'Cancelled'] as $value=>$label)<option value="{{$value}}" @selected(request('status')===$value)>{{$label}}</option>@endforeach</select><button class="quiet">Search</button></div><a class="button outline" href="/staff/export?{{http_build_query(request()->only('search','status'))}}">Export</a></form>
<div class="table-wrap"><table><thead><tr>@foreach($columns as $label)<th>{{$label}}</th>@endforeach<th scope="col" class="table-action-cell">Action</th></tr></thead><tbody>
@forelse($requests as $q)<tr>@foreach($columns as $key=>$label)<td>
 @switch($key)
 @case('ticket')<strong>{{$q->ticket}}</strong>@break
 @case('name'){{$q->user->name}}<small>{{$q->user->student_id}}</small>@break
 @case('course'){{$q->user->course??'—'}}@break
 @case('organization'){{$q->user->organization?->name??'—'}}@break
 @case('kind'){{App\Models\ServiceRequest::kinds()[$q->kind]??$q->kind}}@break
 @case('date'){{$q->created_at->format('M j, Y')}}@break
 @case('event_date'){{isset($q->details['starts_at'])?\Carbon\Carbon::parse($q->details['starts_at'])->format('M j, Y'):'—'}}@break
 @case('requirements'){{count($q->files??[])}} files attached@break
 @case('evidence'){{count($q->files??[])}} Files@break
 @case('status')<span class="status {{$q->status}}">{{ucwords(str_replace('_',' ',$q->status))}}</span>@break
 @default {{$q->details[$key]??'—'}}
 @endswitch
 </td>@endforeach<td class="table-action-cell"><a class="button table-action {{count($q->reviewActions(auth()->user()))?'':'outline'}}" href="{{request()->fullUrlWithQuery(['review'=>$q->id,'report'=>null])}}">{{count($q->reviewActions(auth()->user()))?'Review':'View'}}</a></td></tr>@empty<tr><td colspan="{{count($columns)+1}}" class="empty">No transactions found.</td></tr>@endforelse
</tbody></table></div><div class="pagination"><span>{{$requests->total()}} transactions</span>{{$requests->links()}}@if(auth()->user()->role==='cvu_head')<a class="button quiet table-action" href="?archive={{request()->boolean('archive')?'0':'1'}}">{{request()->boolean('archive')?'Back to Active':'Find Archived'}}</a>@endif</div></div>
