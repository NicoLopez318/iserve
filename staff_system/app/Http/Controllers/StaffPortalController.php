<?php
namespace App\Http\Controllers;

use App\Models\{User,ServiceRequest,Report,Organization,Event,Assignment,Attendance,Journal,Point};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,Hash};
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\Support\StaffDrafts;
use App\Support\LeadershipPoints;
use App\Support\PortalContent;
use App\Support\CommunityServiceHours;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class StaffPortalController extends Controller
{
 public static function menus(string $role):array {
  return match($role){
   'cvu_head'=>['dashboard'=>'Dashboard','complaints'=>'Complaints & Concerns','lost-found'=>'Lost & Found','readmission'=>'Re-Admission','id-replacement'=>'ID Replacement','community'=>'Community Service','seminars'=>'Seminar','clearance'=>'Clearance','reports'=>'Reports','settings'=>'Settings'],
   'sfu_head'=>['dashboard'=>'Dashboard','applications'=>'Discount Applications','reports'=>'Reports','settings'=>'Settings'],
   'slu_head'=>['dashboard'=>'Dashboard','organizations'=>'Organizations','proposals'=>'Proposals','events'=>'Events & Activities','achievements'=>'Achievements','points'=>'Leaderboards & Points','reports'=>'Reports','content'=>'Portal Content','settings'=>'Settings'],
   'assistant'=>['dashboard'=>'Dashboard','inbox'=>'Report Inbox','leadership'=>'Student Leadership','scholarship'=>'Scholarship & Financial','values'=>'Character & Values','history'=>'Submission History','settings'=>'Settings'],
   'admin'=>['dashboard'=>'Dashboard','inbox'=>'Report for Review','organization-documents'=>'Organization Documents','leadership'=>'Student Leadership','scholarship'=>'Scholarship & Financial','values'=>'Character & Values','history'=>'Review History','settings'=>'Settings'],
   default=>[],
  };
 }
 private function scope(Request $r){return ServiceRequest::with(['user','reviewer'])->when(!in_array($r->user()->role,['admin','assistant']),fn($q)=>$q->whereIn('kind',config('portal.office.'.$r->user()->role,[])));}
 private function reports(Request $r){return Report::with('user')->when(!in_array($r->user()->role,['admin','assistant']),fn($q)=>$q->where('user_id',$r->user()->id));}
 private function audit(Request $r,string $action,string $subject,string $details=''){DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>$action,'subject'=>$subject,'details'=>$details,'created_at'=>now()]);}
 private function unitRole(?string $unit):?string{return ['leadership'=>'slu_head','scholarship'=>'sfu_head','values'=>'cvu_head'][$unit]??null;}
 private function only(Request $r,string $role):void{abort_unless($r->user()->role===$role,403);}
 public function organizationDocuments(Request $request)
 {
  $this->only($request,'admin');
  $filters=$request->validate(['organization'=>'nullable|integer|min:1','search'=>'nullable|string|max:120']);
  $organizations=Organization::orderBy('name')->get()->keyBy('id');
  $query=ServiceRequest::with('user.organization')->whereIn('kind',['activity','achievement'])
   ->whereNotNull('files')->whereNotIn('files',['[]','{}','null']);

  if(isset($filters['organization'])){
   $organizationId=$request->integer('organization');
   $this->filterRequestOrganization($query,[$organizationId,(string)$organizationId]);
  }
  if(filled($filters['search']??null)){
   $term='%'.$filters['search'].'%';
   $query->where(fn($matches)=>$matches->where('ticket','like',$term)->orWhere('details->title','like',$term)
    ->orWhere('files','like',$term)->orWhereHas('user',fn($users)=>$users->where('name','like',$term)));
  }
  $submissions=$query->latest()->orderByDesc('id')->paginate(15)->appends($filters);
  $menus=self::menus('admin');
  $page='organization-documents';
  $title=$menus[$page];
  $description='Browse attachments from organization proposals, completion reports, and achievements.';

  return view('workspace.organization-documents',compact('organizations','submissions','filters','menus','page','title','description'));
 }
 private function metadata(string $key):array{return json_decode(DB::table('settings')->where('key','staff.'.$key)->value('value')??'[]',true)??[];}
 private function saveMetadata(string $key,array $data):void{DB::table('settings')->updateOrInsert(['key'=>'staff.'.$key],['value'=>json_encode($data)]);}
 private function snapshot(Request $r,string $from,string $to):array{
  $start=$from.' 00:00:00';$end=$to.' 23:59:59';$rows=$this->scope($r)->whereBetween('created_at',[$start,$end])->get()->map(fn($q)=>['ticket'=>$q->ticket,'kind'=>$q->kind,'status'=>$q->status,'name'=>$q->user->name,'student_id'=>$q->user->student_id,'rate'=>$q->details['discount_rate']??0])->all();
  $historical=$this->scope($r)->where('created_at','<=',$end)->get();$pending=[];
  foreach($historical as $q){$event=DB::table('audit_logs')->where('subject',$q->ticket)->where('created_at','<=',$end)->whereIn('action',['request.in_review','request.needs_revision','request.unit_approved','request.awaiting_admin','request.approved','request.rejected','request.cancelled','request.resubmitted'])->latest('id')->first();$state=$event?str_replace('request.','',$event->action):($q->updated_at->lte(\Carbon\Carbon::parse($end))?$q->status:'pending');if($state==='resubmitted')$state='pending';if(in_array($state,['pending','in_review','needs_revision','unit_approved','awaiting_admin']))$pending[$q->kind]=($pending[$q->kind]??0)+1;}
  $activity=[];foreach(config('portal.office.'.$r->user()->role,[]) as $kind)$activity[]=['service'=>ServiceRequest::kinds()[$kind],'completed'=>$this->scope($r)->where('kind',$kind)->where('status','approved')->whereBetween('reviewed_at',[$start,$end])->count(),'pending'=>$pending[$kind]??0];
  if($r->user()->role==='cvu_head'){$activity[]=['service'=>'Community Service','completed'=>Assignment::where('status','completed')->whereBetween('updated_at',[$start,$end])->count(),'pending'=>Assignment::where('created_at','<=',$end)->where(fn($q)=>$q->where('status','active')->orWhere('updated_at','>',$end))->count()];$activity[]=['service'=>'Seminars Held','completed'=>Event::where('type','seminar')->where('status','!=','cancelled')->whereBetween('starts_at',[$start,$end])->count(),'pending'=>Attendance::whereHas('event',fn($q)=>$q->where('type','seminar'))->where('status','pending')->where('created_at','<=',$end)->count()];}
  if($r->user()->role==='slu_head'){$activity[]=['service'=>'Events Completed','completed'=>Event::where('type','leadership')->where('status','completed')->whereBetween('ends_at',[$start,$end])->count(),'pending'=>Event::where('type','leadership')->where('status','published')->where('created_at','<=',$end)->count()];$activity[]=['service'=>'Verified Attendance','completed'=>Attendance::whereHas('event',fn($q)=>$q->where('type','leadership'))->where('status','approved')->whereBetween('time_in',[$start,$end])->count(),'pending'=>Attendance::whereHas('event',fn($q)=>$q->where('type','leadership'))->where('status','pending')->where('created_at','<=',$end)->count()];}
  return ['records'=>$rows,'activity'=>$activity,'unit'=>$r->user()->role,'date_from'=>$from,'date_to'=>$to];
 }
 private function cardValue(Request $r,string $label,string $page):int {
  $label=strtolower(trim($label));$q=$this->scope($r);$kinds=['complaints'=>['complaint'],'lost-found'=>['lost','found'],'readmission'=>['readmission'],'id-replacement'=>['id-replacement'],'applications'=>['scholarship']];if(isset($kinds[$page]))$q->whereIn('kind',$kinds[$page]);
  if($page==='readmission'){
   $status=match($label){'new request'=>'pending','for review'=>'in_review','interview scheduled'=>'interview_scheduled','approved'=>'approved',default=>null};
   return $q->when($r->boolean('archive'),fn($items)=>$items->where('details->archived',true),
    fn($items)=>$items->where(fn($active)=>$active->whereNull('details->archived')->orWhere('details->archived',false)))
    ->when($status,fn($items)=>$items->withReadmissionQueueStatus($status))->count();
  }
  if($page==='id-replacement'){
   $q->when($r->boolean('archive'),fn($items)=>$items->where('details->archived',true),
    fn($items)=>$items->where(fn($active)=>$active->whereNull('details->archived')->orWhere('details->archived',false)));
   return match($label){
    'for review'=>$q->whereIn('status',['pending','in_review'])->count(),
    'approved'=>$q->awaitingRelease()->count(),
    'released'=>$q->withIdReplacementQueueStatus('released')->count(),
    default=>$q->count(),
   };
  }
  if($r->user()->role==='admin'&&str_contains($label,'awaiting review'))return $this->reports($r)->where('status','awaiting_admin')->whereNull('snapshot->admin_review_started_at')->count();
  if($r->user()->role==='admin'&&str_contains($label,'under admin'))return $this->reports($r)->where('status','awaiting_admin')->whereNotNull('snapshot->admin_review_started_at')->count();
  if($page==='reports'||in_array($r->user()->role,['admin','assistant'])){
   $reports=$this->reports($r);$status=match(true){str_contains($label,'draft')=>'draft',str_contains($label,'returned assistant')=>'assistant_revision',str_contains($label,'return'),str_contains($label,'follow-up')=>'needs_revision',str_contains($label,'admin review'),str_contains($label,'forward to')=>'awaiting_admin',str_contains($label,'acknowledge')=>'approved',default=>'pending'};
   if(str_contains($label,'overdue'))return $reports->whereIn('status',['pending','awaiting_admin'])->where('created_at','<',now()->subDay())->count();return $reports->where('status',$status)->count();
  }
  return match(true){
   str_contains($label,'overall student')=>Assignment::count(),
   str_contains($label,'on duty')=>Attendance::whereNotNull('assignment_id')->whereDate('time_in',today())->count(),
   str_contains($label,'from complaints')=>Assignment::where('offense','like','%CMP%')->count(),
   str_contains($label,'awaiting assignment')=>$q->where('kind','complaint')->where('status','approved')->whereDoesntHave('user.assignment')->count(),
   str_contains($label,'recognized')=>Organization::count(),
   str_contains($label,'proposals')=>$q->whereIn('kind',['leadership','activity'])->whereIn('status',['pending','in_review'])->count(),
   str_contains($label,'achievements')=>$q->where('kind','achievement')->whereIn('status',['pending','in_review'])->count(),
   str_contains($label,'total seminar')=>Event::where('type','seminar')->count(),
   str_contains($label,'upcoming')=>Event::where('type',$page==='seminars'?'seminar':'leadership')->where('status','published')->where('starts_at','>',now())->count(),
   str_contains($label,'ongoing seminar')=>Event::where('type','seminar')->where('status','published')->where('starts_at','<=',now())->where('ends_at','>=',now())->count(),
   str_contains($label,'verification')=>Attendance::where('status','pending')->whereHas('event',fn($e)=>$e->where('type','seminar'))->count(),
   str_contains($label,'community')=>Assignment::where('status','active')->count(),
   str_contains($label,'clients today')=>$q->whereDate('created_at',today())->count(),
   str_contains($label,'completed today')=>$q->where('status','approved')->whereDate('reviewed_at',today())->count(),
   str_contains($label,'lost reports')=>$q->where('kind','lost')->count(),
   str_contains($label,'found reports')=>$q->where('kind','found')->count(),
   str_contains($label,'resolved'),str_contains($label,'approved'),str_contains($label,'processed'),str_contains($label,'cleared')=>$q->where('status','approved')->count(),
   str_contains($label,'released')=>$q->where('details->released',true)->count(),
   $page==='lost-found'&&$label==='pending claims'=>$q->awaitingRelease()->count(),
   str_contains($label,'interview')=>$q->whereNotNull('details->interview_at')->count(),
   str_contains($label,'appointments today')=>$q->where('kind','clearance')->where('details->appointment_date',today()->format('Y-m-d'))->count(),
   str_contains($label,'compliance'),str_contains($label,'incomplete'),str_contains($label,'awaiting requirements')=>$q->where('status','needs_revision')->count(),
   str_contains($label,'endorsement')=>$q->where('status','unit_approved')->count(),
   str_contains($label,'pending'),str_contains($label,'for review'),str_contains($label,'validation')=>$q->whereIn('status',['pending','in_review'])->count(),
   str_contains($label,'new')=>$q->where('status','pending')->count(),
   default=>$q->count(),
  };
 }
 public function notifications(Request $r){
  $user=$r->user();
  $items=$user->notifications()->latest()->get();
  $unreadIds=$items->whereNull('read_at')->modelKeys();
  if($unreadIds)$user->unreadNotifications()->whereKey($unreadIds)->update(['read_at'=>now()]);

  if($user->isStaff()){
   return view('workspace.notifications',['items'=>$items,'menus'=>self::menus($user->role),'page'=>'notifications','title'=>'Notifications']);
  }
  return view('portal.notifications',compact('items'));
 }

 public function page(Request $r,string $page='dashboard'){
  $menus=self::menus($r->user()->role);abort_unless(isset($menus[$page]),404);
  $sfuDashboard=$page==='dashboard'&&$r->user()->role==='sfu_head';
  $sluDashboard=$page==='dashboard'&&$r->user()->role==='slu_head';
  $cvuDashboard=$page==='dashboard'&&$r->user()->role==='cvu_head';
  $cvuComplaints=$page==='complaints'&&$r->user()->role==='cvu_head';
  $cvuLostFound=$page==='lost-found'&&$r->user()->role==='cvu_head';
  $cvuReadmission=$page==='readmission'&&$r->user()->role==='cvu_head';
  $cvuIdReplacement=$page==='id-replacement'&&$r->user()->role==='cvu_head';
  $sluProposals=$page==='proposals'&&$r->user()->role==='slu_head';
  $sluAchievements=$page==='achievements'&&$r->user()->role==='slu_head';
  $fullProposalQueue=$sluProposals&&$r->query('queue')==='all';
  $sluOrganizations=$page==='organizations'&&$r->user()->role==='slu_head';
  $membershipTab=$sluOrganizations&&($r->query('tab')==='membership'||($r->filled('review')&&$r->query('tab')!=='directory'));
  $fullSluQueue=$sluDashboard&&$r->query('queue')==='all';
  $sfuQueue=$sfuDashboard||($page==='applications'&&$r->user()->role==='sfu_head');
  $sfuReports=$page==='reports'&&$r->user()->role==='sfu_head';
  $sluReports=$page==='reports'&&$r->user()->role==='slu_head';
  $scope=$this->scope($r);$kinds=['complaints'=>['complaint'],'lost-found'=>['lost','found'],'readmission'=>['readmission'],'id-replacement'=>['id-replacement'],'applications'=>['scholarship'],'organizations'=>['leadership'],'proposals'=>['activity'],'achievements'=>['achievement'],'clearance'=>['clearance'],'seminars'=>['seminar']];
  if(isset($kinds[$page]))$scope->whereIn('kind',$kinds[$page]);
  $archivedRequestCount=$cvuComplaints||$cvuLostFound||$cvuReadmission
   ?(clone $scope)->where('details->archived',true)->count()
   :0;
  if($unit=$this->unitRole($page))$scope->whereIn('kind',config('portal.office.'.$unit));
  $scope->when($r->boolean('archive'),fn($q)=>$q->where('details->archived',true),fn($q)=>$q->where(fn($x)=>$x->whereNull('details->archived')->orWhere('details->archived',false)));
  $achievementCategories=collect();
  if($sluAchievements){
   $scope->where(fn($query)=>$query->whereNull('details->adjustment')->orWhere('details->adjustment',false));
   $achievementCategories=(clone $scope)->toBase()->select('details->category as category','details->award as award')
    ->distinct()->get()->map(fn($item)=>filled($item->category)?$item->category:$item->award)
    ->filter(fn($category)=>filled($category))->unique()->sort(SORT_NATURAL|SORT_FLAG_CASE);
   if($r->filled('category')){
    $category=$r->string('category')->toString();
    $scope->where(fn($query)=>$query->where('details->category',$category)
     ->orWhere(fn($legacy)=>$legacy->where(fn($empty)=>$empty->whereNull('details->category')->orWhere('details->category',''))
      ->where('details->award',$category)));
   }
  }
  if($r->filled('search')){
   $search=$r->string('search')->trim()->toString();
   $term='%'.$search.'%';
   $reportKinds=$cvuLostFound?array_keys(array_filter(ServiceRequest::LOST_FOUND_TYPES,
    fn($label)=>stripos($label,$search)!==false)):[];
   $scope->where(fn($query)=>$query->where('ticket','like',$term)
    ->orWhereHas('user',fn($user)=>$user->where('name','like',$term)->orWhere('student_id','like',$term))
    ->when($cvuComplaints,fn($complaints)=>$complaints->orWhere('details->respondent','like',$term)
     ->orWhere('details->complaint_type','like',$term)->orWhere('details->other_complaint','like',$term))
    ->when($cvuLostFound,fn($items)=>$items->orWhere('details->item_name','like',$term)
     ->orWhereIn('kind',$reportKinds))
    ->when($cvuReadmission,fn($items)=>$items->orWhere('details->subject','like',$term)
     ->orWhere('details->subject_code','like',$term)->orWhere('details->applicant->name','like',$term))
    ->when($cvuIdReplacement,fn($items)=>$items->orWhere('details->replacement_reason','like',$term)->orWhere('details->student_name','like',$term))
    ->when($membershipTab,fn($membership)=>$membership->orWhereIn('details->organization_id',Organization::select('id')->where('name','like',$term)))
    ->when($sluProposals||$sluAchievements,fn($submission)=>$submission->orWhere('details->title','like',$term)
     ->orWhere(fn($organization)=>$this->filterRequestOrganization($organization,Organization::select('id')->where('name','like',$term)))));
  }
  if($membershipTab&&$r->filled('organization')){
   $organizationId=$r->integer('organization');
   $scope->whereIn('details->organization_id',[$organizationId,(string)$organizationId]);
  }
  if(($sluProposals||$sluAchievements)&&$r->filled('organization')){
   $this->filterRequestOrganization($scope,Organization::select('id')->whereKey($r->integer('organization')));
  }
  $scope->when($r->filled('status'),fn($q)=>match(true){
   $sfuQueue&&$r->status==='pending'=>$q->whereIn('status',['pending','in_review']),
   $cvuLostFound&&$r->status==='claim_pending'=>$q->awaitingRelease(),
   $cvuLostFound&&$r->status==='released'=>$q->where('details->released',true),
   $cvuReadmission=>$q->withReadmissionQueueStatus($r->string('status')->toString()),
   $cvuIdReplacement=>$q->withIdReplacementQueueStatus($r->string('status')->toString()),
   default=>$q->where('status',$r->status),
  });
  $scope->when($sfuQueue&&$r->filled('program'),fn($q)=>$q->whereHas('user',fn($user)=>$user->where('course',$r->string('program')->toString())));
  if($page==='points')$scope->where('details->adjustment',true)->whereIn('status',['unit_approved','awaiting_admin']);
  if($sluDashboard&&!$fullSluQueue&&!$r->filled('status'))$scope->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin']);
  $queueSize=$fullSluQueue||$fullProposalQueue?15:(($sfuDashboard||$sluDashboard||$sluProposals)?4:5);
  $requests=$scope->latest()->when($sluProposals||$sluAchievements||$cvuLostFound||$cvuReadmission||$cvuIdReplacement,fn($query)=>$query->orderByDesc('id'))
   ->paginate($queueSize,['*'],'transactions')->withQueryString();
  if(($sfuQueue&&!$sfuDashboard)||$membershipTab||$sluProposals||$sluAchievements||$page==='points')$requests->appends(['review'=>null,'report'=>null]);
  $reportQuery=$this->reports($r)->when($unit,fn($q)=>$q->whereHas('user',fn($u)=>$u->where('role',$unit)));
  $reportPeriods=$sluReports?$this->filterSluReportPeriod($r,$reportQuery):[];
  $sluReportActivity=$sluReports?$this->scope($r)->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total','kind'):collect();
  if($r->filled('search')){
   $search=$r->string('search')->trim()->toString();
   $reportId=$sluReports&&preg_match('/\A(?:RPT-SLU-)?([0-9]+)\z/i',$search,$matches)?(int)$matches[1]:null;
   $reportQuery->where(fn($q)=>$q->where('title','like','%'.$search.'%')->when($reportId,fn($match)=>$match->orWhere('id',$reportId)));
  }
  $reports=$reportQuery->when($r->filled('status'),fn($q)=>$q->where('status',$r->status))
   ->when($sfuReports&&$r->filled('semester'),fn($q)=>$q->where('semester',$r->string('semester')->toString()))
   ->latest()->when($sluReports,fn($q)=>$q->orderByDesc('id'))->paginate(in_array($r->user()->role,['admin','assistant'])?4:5,['*'],'reports');
  $reports->appends($sfuReports||$sluReports?$r->except(['report','review']):$r->query());
  $counts=$this->scope($r)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status');
  $reportCounts=$this->reports($r)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status');
  $organizations=Organization::withCount('members')->get();
  $events=Event::withCount(['attendances'=>fn($q)=>$q->when($page==='seminars',fn($x)=>$x->where('status','<>','cancelled'))])->latest()->get();
  $eventVenues=collect();
  $eventCalendar=null;
  if($page==='events'){
   $leadershipEvents=$events->where('type','leadership')->load('attendances.user');
   $eventVenues=Event::venueChoices($leadershipEvents->pluck('venue'));
   $eventCalendar=$this->eventCalendar($r,$leadershipEvents);
  }
  $assignments=Assignment::with(['user','attendances','journals'])->latest()->get();$students=User::where('account_type','student')->get();
  $checkedStudent=null;
  if($page==='community'){
   $r->validate(['check_student'=>'nullable|integer|min:1']);
   if($r->filled('check_student')){
    $checkedStudent=$students->firstWhere('id',$r->integer('check_student'));
    abort_unless($checkedStudent,404);
   }
   $assignments->load('feedbackEntries.author');
  }
  $communityHourLimits=$r->user()->role==='cvu_head'?CommunityServiceHours::limits():[];
  $attendance=Attendance::with(['user','event','assignment'])->where('status','pending')->get();$journals=Journal::with('assignment.user')->where('status','pending')->get();
  $points=Point::with('user')->latest()->get();
  $organizationPoints=($sluOrganizations&&!$membershipTab)?Point::join('users','points.user_id','=','users.id')
   ->whereNotNull('users.organization_id')->selectRaw('users.organization_id, SUM(points.amount) AS total')
   ->groupBy('users.organization_id')->pluck('total','users.organization_id'):collect();
  $leadershipPoints=null;
  $scoringPublication=null;
  $scoringHistory=collect();
  if($page==='points'){
   $leadershipPoints=LeadershipPoints::dashboard($organizations,$points,$r->string('term')->toString(),$r->string('department')->toString());
   $ledger=$leadershipPoints['ledger'];
   $fullLedger=$r->query('ledger')==='all';
   $ledgerPage=max(1,min((int)ceil($ledger->count()/15),$r->integer('ledger_page',1)));
   $leadershipPoints['ledger']=$fullLedger
    ?(new LengthAwarePaginator($ledger->forPage($ledgerPage,15),$ledger->count(),15,$ledgerPage,['path'=>$r->url(),'pageName'=>'ledger_page']))
     ->appends($r->except(['review','report']))
    :$ledger->take(3);
   $pendingAwards=ServiceRequest::where('kind','achievement')
    ->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin']);
   $scoringPublication=[
    'awards'=>(clone $pendingAwards)->where(fn($query)=>$query->whereNull('details->adjustment')->orWhere('details->adjustment',false))->count(),
    'adjustments'=>$pendingAwards->where('details->adjustment',true)->count(),
   ];
   $scoringHistory=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')
    ->where('audit_logs.action','points.rules')->latest('audit_logs.id')->limit(20)
    ->get(['audit_logs.details','audit_logs.created_at','users.name']);
  }
  $unitSettings=$page==='settings'&&in_array($r->user()->role,['sfu_head','slu_head'],true);
  $audit=DB::table('audit_logs')->when(!in_array($r->user()->role,['admin','assistant']),fn($q)=>$q->where('user_id',$r->user()->id))
   ->when($unitSettings,fn($q)=>$q->where('action','account.login'))->latest('id')->limit($unitSettings?20:80)->get();
  $contentLibrary=$page==='content'?$this->contentLibrary($r):null;
  $slots=array_map(fn($slot)=>[...$slot,'end'=>$slot['end']??'—'],$this->metadata('clearance-slots'));$rules=$this->metadata('points-rules');
  $designs=json_decode(file_get_contents(base_path('design-pages.json')),true);$design=$designs[$r->user()->role][$page]??['title'=>$menus[$page],'description'=>''];
  if(!$sluReports&&!$cvuDashboard)foreach($design['cards']??[] as $i=>$card)$design['cards'][$i]['value']=$this->cardValue($r,$card['label'],$page);
  $cvuSummary=collect();$cvuReviewQueue=null;$cvuCommunityStats=[];
  if($cvuDashboard){
   $cvuSummary=$this->scope($r)->whereDate('created_at',today())
    ->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total','kind');
   $pendingReviews=$this->scope($r)->whereIn('status',['pending','in_review'])
    ->where(fn($q)=>$q->whereNull('details->archived')->orWhere('details->archived',false));
   $cvuReviewQueue=$pendingReviews->without(['user','reviewer'])->latest()->orderByDesc('id')
    ->paginate(4,['*'],'reviews')->appends($r->except(['review','report']));
   $cvuCommunityStats=[
    'active'=>$assignments->where('status','active')->count(),
    'verification'=>$attendance->whereNotNull('assignment_id')->count(),
    'completed'=>$assignments->where('status','completed')->filter(fn($item)=>$item->updated_at?->isSameMonth(today()))->count(),
   ];
   $dashboardCounts=[
    'Clients Today'=>$cvuSummary->sum(),
    'Pending Reviews'=>$cvuReviewQueue->total(),
    'Active Community Service'=>$cvuCommunityStats['active'],
    'Completed Today'=>$this->cardValue($r,'Completed Today',$page),
   ];
   foreach($design['cards'] as $i=>$card)$design['cards'][$i]['value']=$dashboardCounts[$card['label']];
  }
  $selectedRequest=null;$selectedHistory=collect();
  if(!$r->filled('report')&&$r->filled('review')){$r->validate(['review'=>'integer|min:1']);$selectedRequest=$this->scope($r)->findOrFail($r->integer('review'));}
  elseif($sfuDashboard)$selectedRequest=$requests->first();
  if($selectedRequest){$selectedRequest->load(['user','reviewer']);$selectedHistory=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject',$selectedRequest->ticket)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();}
  $achievementMatch=null;
  if($sluAchievements&&$selectedRequest?->kind==='achievement'&&empty($selectedRequest->details['adjustment'])){
   $details=$selectedRequest->details??[];
   if(filled($details['title']??null)&&filled($details['achievement_date']??null)){
    $achievementMatch=$this->scope($r)->where('kind','achievement')->where('id','<>',$selectedRequest->id)
     ->where('user_id',$selectedRequest->user_id)->whereNotIn('status',['cancelled','rejected'])
     ->where('details->title',$details['title'])->where('details->achievement_date',$details['achievement_date'])->exists();
   }
  }
  $selectedReport=null;$reportHistory=collect();if($r->filled('report')){$r->validate(['report'=>'integer|min:1']);$selectedReport=$this->reports($r)->findOrFail($r->integer('report'));$reportHistory=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject','report:'.$selectedReport->id)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();}
  $sfuPrograms=$sfuQueue?User::whereIn('id',$this->scope($r)->select('user_id'))
   ->whereNotNull('course')->where('course','<>','')->distinct()->orderBy('course')->pluck('course'):collect();
  $sfuSummary=$sfuDashboard?$this->sfuSemesterSummary($r):null;
  $sluDeadlines=$sluDashboard?$this->sluDeadlines($events):collect();
  $sluActivity=$sluDashboard?$this->sluActivity($r):collect();
  $orgDirectory=$sluOrganizations&&!$membershipTab?$this->organizationDirectory($r,$organizations,$organizationPoints):null;
  $orgForm=$sluOrganizations?$this->organizationFormOptions():null;
  return view('workspace.page',compact('selectedReport','reportHistory','selectedRequest','selectedHistory','page','menus','design','requests','reports','reportPeriods','sluReportActivity','counts','reportCounts','organizations','events','eventVenues','eventCalendar','assignments','students','attendance','journals','points','leadershipPoints','scoringPublication','scoringHistory','audit','contentLibrary','slots','rules','sfuPrograms','sfuSummary','fullSluQueue','fullProposalQueue','sluDeadlines','sluActivity','membershipTab','orgDirectory','orgForm','achievementCategories','achievementMatch','cvuSummary','cvuReviewQueue','cvuCommunityStats','archivedRequestCount','communityHourLimits','checkedStudent'));
 }
 private function contentLibrary(Request $request):array
 {
  $entries=PortalContent::library(DB::table('announcements')->where('unit','leadership')->latest('id')->get());
  $preview=$entries->where('status','published')->sortByDesc('start')->take(6)->values();
  $search=$request->string('search')->trim()->toString();
  $rows=$entries->when($search!=='',fn($list)=>$list->filter(fn($entry)=>str_contains(
   Str::lower(implode(' ',[$entry['item']->title,$entry['item']->body,$entry['type'],$entry['location']])),Str::lower($search)
  )));
  foreach(['type','location','status'] as $filter){
   if($request->filled($filter))$rows=$rows->where($filter,$request->string($filter)->toString());
  }
  $pageSize=8;
  $page=max(1,min((int)ceil($rows->count()/$pageSize),$request->integer('content_page',1)));
  $library=(new LengthAwarePaginator($rows->forPage($page,$pageSize)->values(),$rows->count(),$pageSize,$page,
   ['path'=>$request->url(),'pageName'=>'content_page']))->withQueryString();
  return compact('library','preview');
 }
 private function filterSluReportPeriod(Request $request,\Illuminate\Database\Eloquent\Builder $query):array
 {
  $months=[];$terms=[];
  $periods=(clone $query)->toBase()->select('semester','academic_year','snapshot->date_from as date_from','snapshot->date_to as date_to')->distinct()->get();
  foreach($periods as $period){
   $month=Report::reportingMonth($period->date_from,$period->date_to);
   if($month)$months['month:'.$month]=\Carbon\CarbonImmutable::createFromFormat('!Y-m',$month)->format('F Y');
   $terms['term:'.$period->semester.':'.$period->academic_year]=[$period->semester,$period->academic_year];
  }
  krsort($months);
  $selection=$request->string('period')->toString();
  if(isset($months[$selection])){
   $month=substr($selection,6).'-%';
   $query->where('snapshot->date_from','like',$month)->where('snapshot->date_to','like',$month);
  }elseif(isset($terms[$selection])){
   [$semester,$year]=$terms[$selection];
   $query->where('semester',$semester)->where('academic_year',$year);
  }
  return $months+array_map(fn($term)=>implode(' · ',$term),$terms);
 }
 private function eventCalendar(Request $request,Collection $events):array
 {
  $monthKey=$request->string('month')->toString();
  $selectedMonth=preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/',$monthKey);
  $month=$selectedMonth?\Carbon\CarbonImmutable::createFromFormat('!Y-m',$monthKey):today()->toImmutable()->startOfMonth();
  $now=now();
  [$upcoming,$past]=$events->partition(fn($event)=>$event->starts_at->gte($now));
  $chronological=$upcoming->sortBy('starts_at')->concat($past->sortByDesc('starts_at'));
  $monthEvents=$events->filter(fn($event)=>$event->starts_at->isSameMonth($month))->sortBy('starts_at');
  $days=$monthEvents->groupBy(fn($event)=>$event->starts_at->toDateString());
  $all=$request->query('list')==='all';
  $list=$all?$chronological:($selectedMonth?$monthEvents:$chronological)->take(4);
  $monthChoices=collect(range(-6,6))->map(fn($offset)=>$month->addMonths($offset)->format('Y-m'))
   ->merge($events->map(fn($event)=>$event->starts_at->format('Y-m')))->unique()->sort();
  return compact('month','days','list','all','monthChoices');
 }
 private function filterRequestOrganization($query,$organizationIds)
 {
  return $query->where(fn($proposal)=>$proposal->whereIn('details->organization_id',$organizationIds)
   ->orWhere(fn($fallback)=>$fallback->whereNull('details->organization_id')
    ->whereHas('user',fn($applicant)=>$applicant->whereIn('organization_id',$organizationIds))));
 }
 private function organizationFormOptions():array
 {
  $metadata=DB::table('settings')->where('key','like','staff.organization.%')->pluck('value')->map(fn($value)=>json_decode($value,true)??[]);
  $year=now()->year;
  $academicYear=DB::table('settings')->where('key','academic_year')->value('value')??$year.'-'.($year+1);
  $academicYears=collect([$academicYear])->merge($metadata->pluck('academic_year'))
   ->merge(array_map(fn($start)=>$start.'-'.($start+1),range($year-1,$year+2)))->filter()->unique();
  $departments=$metadata->pluck('college')->filter()->unique()->sort(SORT_NATURAL|SORT_FLAG_CASE);
  $advisers=User::where('account_type','employee')->pluck('name')->merge($metadata->pluck('adviser'))->filter()->unique()->sort(SORT_NATURAL|SORT_FLAG_CASE);
  return compact('academicYear','academicYears','departments','advisers');
 }
 private function organizationDirectory(Request $request,Collection $organizations,Collection $points):array
 {
  $metadata=DB::table('settings')->whereIn('key',$organizations->map(fn($organization)=>'staff.organization.'.$organization->id)->all())->pluck('value','key');
  $statuses=['active'=>'Active','for_renewal'=>'For Renewal','needs_attention'=>'Needs Attention'];
  $rows=$organizations->sortBy('name',SORT_NATURAL|SORT_FLAG_CASE)->map(function($organization)use($metadata,$points,$statuses){
   $details=json_decode($metadata['staff.organization.'.$organization->id]??'[]',true)??[];
   $status=Str::of($details['status']??'active')->lower()->replace([' ','-'],'_')->toString();
   return [
    'organization'=>$organization,'college'=>$details['college']??'','adviser'=>$details['adviser']??'',
    'acronym'=>$details['acronym']??'','status'=>isset($statuses[$status])?$status:'active',
    'points'=>(int)($points[$organization->id]??0),
   ];
  });
  $departments=$rows->pluck('college')->filter()->unique()->sort(SORT_NATURAL|SORT_FLAG_CASE);
  $search=$request->string('organization_search')->trim()->toString();
  $department=$request->string('department')->toString();
  $status=$request->string('organization_status')->toString();
  $rows=$rows->filter(fn($row)=>
   (!$search||Str::contains(implode(' ',[$row['organization']->name,$row['acronym'],$row['college'],$row['adviser']]),$search,true))
   && (!$department||$row['college']===$department)
   && (!$status||$row['status']===$status)
  );
  $organizationIds=$rows->pluck('organization.id');
  $organizations->whereIn('id',$organizationIds)->load('members');
  $achievements=ServiceRequest::with('user')->where('kind','achievement')->where('status','approved')
   ->where(fn($query)=>$query->whereNull('details->adjustment')->orWhere('details->adjustment',false))
   ->whereHas('user',fn($query)=>$query->whereIn('organization_id',$organizationIds))
   ->latest()->get()->groupBy(fn($achievement)=>$achievement->user->organization_id);
  return compact('rows','departments','statuses','achievements');
 }
 private function sluDeadlines(Collection $events):Collection
 {
  $published=$events->where('type','leadership')->where('status','published');
  $sources=DB::table('settings')->whereIn('key',$published->map(fn($event)=>'staff.event-source.'.$event->id)->all())->pluck('value','key');
  $reported=ServiceRequest::where('kind','activity')->where('details->completion_report',true)
   ->whereNotIn('status',['cancelled','rejected'])->pluck('details')->map(fn($details)=>$details['event_id']??null)->filter()->flip();
  $deadlines=$published->flatMap(function($event)use($sources,$reported){
   $source=json_decode($sources['staff.event-source.'.$event->id]??'[]',true)??[];
   $entries=[];
   if($event->starts_at->isFuture())$entries[]=['date'=>$event->starts_at,'label'=>'Event starts'];
   if(!$reported->has($event->id)&&isset($source['report_due_at'])) {
    $due=\Carbon\Carbon::parse($source['report_due_at']);
    if($due->isFuture())$entries[]=['date'=>$due,'label'=>'Completion report due'];
   }
   return array_map(fn($entry)=>[...$entry,'title'=>$event->title,'url'=>'/workspace/events#event-'.$event->id.'-entry'],$entries);
  });
  return $deadlines->sortBy('date')->take(3);
 }
 private function sluActivity(Request $request):Collection
 {
  $transactions=$this->scope($request);
  $audit=DB::table('audit_logs as audit')->leftJoin('users','users.id','=','audit.user_id')
   ->where(function($query)use($request,$transactions){
    $query->where(fn($own)=>$own->where('audit.user_id',$request->user()->id)->whereIn('audit.action',[
     'organization.create','organization.member','event.create','event.update','event.cancel','report.draft','report.pending',
    ]))->orWhere(fn($related)=>$related->where('audit.action','like','request.%')->whereIn('audit.subject',(clone $transactions)->select('ticket')));
   })->latest('audit.id')->limit(4)->get(['audit.action','audit.subject','audit.created_at','users.name']);

  $entries=$audit->map(function($entry){
   $action=match($entry->action){
    'request.needs_revision'=>'returned a submission for revision',
    'request.resubmitted'=>'resubmitted a request',
    'request.unit_approved'=>'endorsed a submission',
    'request.awaiting_admin'=>'forwarded a submission',
    'request.approved'=>'approved a submission',
    'organization.member'=>'updated an organization roster',
    'organization.create'=>'created an organization',
    'event.create'=>'created an event',
    'event.update'=>'updated an event',
    'event.cancel'=>'cancelled an event',
    'report.draft'=>'saved a report draft',
    'report.pending'=>'submitted a report',
    default=>'updated a submission',
   };
   return ['title'=>($entry->name??'Staff').' '.$action,'subject'=>$entry->subject,'date'=>\Carbon\Carbon::parse($entry->created_at)];
  });
  return $entries->concat($transactions->latest()->limit(3)->get()->map(fn($item)=>[
   'title'=>$item->user->name.' submitted '.match($item->kind){
    'achievement'=>'achievement evidence','leadership'=>'a membership application',default=>($item->details['completion_report']??false)?'an event report':'an activity proposal',
   },
   'subject'=>$item->details['title']??$item->ticket,'date'=>$item->created_at,
  ]))->sortByDesc('date')->take(3);
 }
 private function sfuSemesterSummary(Request $request):array
 {
  $applications=$this->scope($request);
  $settings=DB::table('settings')->whereIn('key',['academic_year','semester'])->pluck('value','key');
  $latestTerm=(clone $applications)->whereNotNull('details->academic_year')->latest()->value('details') ?? [];
  $year=$settings['academic_year']??$latestTerm['academic_year']??null;
  $semester=$settings['semester']??$latestTerm['semester']??null;

  if ($year) {
   $years=[$year];
   if (preg_match('/^(\d{4})\s*[-–—]\s*(\d{4})$/u',trim($year),$parts)) {
    foreach (['-',' - ','–',' – ','—',' — '] as $separator) $years[]=$parts[1].$separator.$parts[2];
   }
   $applications->whereIn('details->academic_year',array_unique($years));
  }
  if ($semester) $applications->where('details->semester',$semester);

  $counts=(clone $applications)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status');
  $processingDays=$applications->where('status','approved')->whereNotNull('reviewed_at')
   ->get(['created_at','reviewed_at'])->avg(fn($application)=>$application->created_at->diffInDays($application->reviewed_at,true));

  return [
   'year'=>$year,
   'semester'=>$semester,
   'received'=>$counts->sum(),
   'processed'=>$counts['approved']??0,
   'incomplete'=>$counts['needs_revision']??0,
   'in_progress'=>$counts->only(['pending','in_review','unit_approved','awaiting_admin'])->sum(),
   'processing_days'=>$processingDays===null?null:(int) round($processingDays),
  ];
 }
 public function request(Request $r,ServiceRequest $serviceRequest){
  abort_unless(in_array($r->user()->role,['admin','assistant'])||$serviceRequest->office()===$r->user()->role,403);
  $serviceRequest->load(['user','reviewer']);$history=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject',$serviceRequest->ticket)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();
  return view('workspace.request',['item'=>$serviceRequest,'history'=>$history,'menus'=>self::menus($r->user()->role)]);
 }
 public function complaintParentGuardian(Request $request, ServiceRequest $serviceRequest)
 {
  $this->only($request,'cvu_head');
  abort_unless($serviceRequest->kind==='complaint',404);
  $contact=$request->validate([
   'parent_guardian_name'=>'sometimes|nullable|string|max:120',
   'parent_guardian_relationship'=>'sometimes|nullable|string|max:60',
   'parent_guardian_phone'=>'sometimes|nullable|string|max:40',
  ]);

  DB::transaction(function()use($request,$serviceRequest,$contact){
   $complaint=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
   abort_unless($complaint->canEditComplaintContact(),403);
   $details=array_diff_key($complaint->details,$contact)+array_filter($contact,fn($value)=>filled($value));
   $complaint->update(['details'=>$details]);
   $this->audit($request,'request.parent_guardian_updated',$complaint->ticket);
  });

  return back()->with('parent_guardian_saved','Parent or guardian contact saved.');
 }
 public function requestNote(Request $r,ServiceRequest $serviceRequest){
  abort_unless($r->user()->role===$serviceRequest->office()&&in_array($serviceRequest->status,['pending','in_review']),403);
  $action=null;$status=null;
  $actionField=$serviceRequest->kind.'_action';
  if($r->has('lost_found_action')&&isset(ServiceRequest::LOST_FOUND_TYPES[$serviceRequest->kind])){
   $decision=$r->validate([
    'lost_found_action'=>'required|in:save,request-info,validate',
    'status'=>'nullable|in:rejected',
   ]);
   $action=$decision['lost_found_action'];
   $status=match($action){
    'request-info'=>'needs_revision',
    'validate'=>'unit_approved',
    'save'=>$decision['status']??null,
   };
  }elseif($serviceRequest->kind==='id-replacement'&&$r->has('id_replacement_action')){
   $decision=$r->validate([
    'id_replacement_action'=>'required|in:save,request-info,approve',
    'status'=>'nullable|in:rejected',
   ]);
   $action=$decision['id_replacement_action'];
   $status=match($action){
    'request-info'=>'needs_revision',
    'approve'=>'unit_approved',
    'save'=>$decision['status']??null,
   };
  }elseif(in_array($serviceRequest->kind,['complaint','readmission'],true)&&$r->has($actionField)){
   $decision=$r->validate([
    $actionField=>'required|in:save,request-info,schedule',
    'status'=>'nullable|in:unit_approved,rejected',
   ]);
   $action=$decision[$actionField];
   $status=match($action){
    'request-info'=>'needs_revision',
    'save'=>$decision['status']??null,
    default=>null,
   };
  }
  if($status!==null){
   $r->merge(['status'=>$status]);
   return app(StaffController::class)->review($r,$serviceRequest);
  }
  $interviewRules=match($action){
   'schedule'=>'required|date|after:now',
   'save'=>'exclude',
   default=>'nullable|date|after:now',
  };
  if($serviceRequest->kind==='readmission'&&$action===null&&!$r->filled('interview_at'))$interviewRules='exclude';
  $v=$r->validate(['review_note'=>'required|string|min:5|max:3000','interview_at'=>$interviewRules,'appointment_date'=>'nullable|date','schedule'=>'nullable|date_format:H:i','identity_verified'=>'nullable|boolean','hymn_completed'=>'nullable|boolean',
   'meeting_type'=>$serviceRequest->kind==='complaint'&&$action==='schedule'?'sometimes|required|in:Interview,Case Conference':'exclude']);
  DB::transaction(function()use($r,$serviceRequest,$v){
   if($serviceRequest->kind==='clearance'){DB::table('settings')->insertOrIgnore(['key'=>'staff.clearance-slots','value'=>'[]']);DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->first();}
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);$this->requireCondition(in_array($q->status,['pending','in_review']),422);$details=$q->details;
   if($q->kind==='clearance'&&(isset($v['appointment_date'])||isset($v['schedule']))){
    $date=$v['appointment_date']??$details['appointment_date'];$time=$v['schedule']??$details['schedule'];$key=$date.' '.$time;$changed=$key!==(($details['appointment_date']??'').' '.($details['schedule']??''));
    $slots=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);$slot=$slots[$key]??null;
    if($changed){$this->requireCondition(\Carbon\Carbon::parse($key)->isFuture()&&$slot&&($slot['open']??false),422,'Choose an open future appointment.');$booked=ServiceRequest::where('kind','clearance')->where('id','!=',$q->id)->whereNotIn('status',['rejected','cancelled'])->where('details->appointment_date',$date)->where('details->schedule',$time)->count();$this->requireCondition($booked<(int)$slot['capacity'],422,'This appointment slot is full.');}
    $details['appointment_date']=$date;$details['schedule']=$time;$details['appointment_slot']=$key;$details['appointment_status']='confirmed';
   }
   foreach(['interview_at','identity_verified','hymn_completed','meeting_type'] as $key)if(array_key_exists($key,$v))$details[$key]=$v[$key];
   $q->update(['details'=>$details,'status'=>'in_review','review_note'=>$v['review_note'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);$this->audit($r,'request.review_saved',$q->ticket,$v['review_note']);
   if(isset($v['interview_at'])||$q->kind==='clearance'){
    $studentUrl=rtrim(config('app.student_portal_url','http://127.0.0.1:8001'),'/').(in_array($q->kind,['readmission','clearance','seminar'])?'/cvu/submitted/':'/requests/').$q->id;
    if($q->user->preferences['in_app']??true)$q->user->notifications()->create(['id'=>Str::uuid(),'type'=>'request','data'=>['title'=>$q->ticket.' schedule updated','url'=>$studentUrl,'message'=>$v['review_note']]]);
    if(isset($v['interview_at'])||isset($v['appointment_date'])||isset($v['schedule'])){
     \App\Support\TransactionEmail::sendAfterCommit(
      $q->user,
      'iServe schedule update: '.$q->ticket,
      "The schedule for your request {$q->ticket} was saved. Sign in to view the appointment details.\n\nView your request: {$studentUrl}",
      ['request_id'=>$q->id],
     );
    }
   }
  });return back()->with('success','Review saved.');
 }
 public function slip(Request $r,ServiceRequest $serviceRequest)
 {
  abort_unless((in_array($r->user()->role,['admin','assistant'])||$r->user()->role===$serviceRequest->office())&&$serviceRequest->status==='approved',403);
  return view($serviceRequest->kind==='readmission'?'workspace.readmission-slip':'workspace.slip',['item'=>$serviceRequest]);
 }
 public function sendReadmissionSlip(Request $request,ServiceRequest $serviceRequest)
 {
  $this->only($request,'cvu_head');
  $message=DB::transaction(function()use($request,$serviceRequest){
   $item=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
   abort_unless($item->kind==='readmission'&&$item->status==='approved',403);
   $student=$item->user;
   $inApp=$student->preferences['in_app']??true;
   $email=$student->preferences['email']??true;
   if(!$inApp&&!$email)return 'The slip is available in the student portal. The student has turned off notifications.';

   $title='Re-Admission Virtual Slip: '.$item->ticket;
   $body='Your approved re-admission slip is available in your student portal.';
   $url=rtrim(config('app.student_portal_url'),'/').'/requests/'.$item->id.'/slip';
   if($inApp)$student->notifications()->create([
    'id'=>Str::uuid(),'type'=>'request','data'=>['title'=>$title,'message'=>$body,'url'=>$url],
   ]);
   \App\Support\TransactionEmail::sendAfterCommit($student,$title,$body."\n\nView your slip: ".$url,['request_id'=>$item->id]);
   $this->audit($request,'request.slip_shared',$item->ticket);
   return 'Slip shared with the student.';
  });
  return redirect('/workspace/request/'.$serviceRequest->id.'/slip')->with('success',$message);
 }
 public function report(Request $r,Report $report){abort_unless(in_array($r->user()->role,['admin','assistant'])||$report->user_id===$r->user()->id,403);$report->load('user');$history=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject','report:'.$report->id)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();return view('workspace.report',['item'=>$report,'history'=>$history,'menus'=>self::menus($r->user()->role)]);}
 public function reportStart(Request $r,Report $report){$this->only($r,'admin');DB::transaction(function()use($r,$report){$q=Report::lockForUpdate()->findOrFail($report->id);abort_unless($q->status==='awaiting_admin',422);$snapshot=$q->snapshot;if(!isset($snapshot['records']))$snapshot=['records'=>$snapshot,'version'=>1];if(!isset($snapshot['admin_review_started_at'])){$snapshot['admin_review_started_at']=now()->toIso8601String();$q->update(['snapshot'=>$snapshot]);$this->audit($r,'report.review_started','report:'.$q->id);}});return redirect(isset(self::menus($r->user()->role)[$r->input('page')??''])?'/workspace/'.$r->input('page').'?report='.$report->id:'/workspace/report/'.$report->id);}
 public function reportStore(Request $r){
  abort_unless(in_array($r->user()->role,['cvu_head','slu_head','sfu_head']),403);$v=$r->validate(['title'=>'required|string|max:150','academic_year'=>'required|string|max:30','semester'=>'required|in:1st Semester,2nd Semester,Summer','summary'=>'required|string|min:30|max:5000','date_from'=>'required|date','date_to'=>'required|date|after_or_equal:date_from','draft'=>'nullable|boolean','attachments'=>'nullable|array|max:5','attachments.*'=>'file|max:10240|mimes:pdf,png,jpg,jpeg,xlsx,csv,docx']);
  $snapshot=$this->snapshot($r,$v['date_from'],$v['date_to']);
  $files=[];foreach($r->file('attachments',[]) as $f)$files[]=['path'=>$f->store('staff-reports'),'name'=>$f->getClientOriginalName(),'size'=>$f->getSize()];
  $item=Report::create(['user_id'=>$r->user()->id,'title'=>$v['title'],'academic_year'=>$v['academic_year'],'semester'=>$v['semester'],'summary'=>$v['summary'],'snapshot'=>[...$snapshot,'files'=>$files,'version'=>1,'versions'=>[]],'status'=>$r->boolean('draft')?'draft':'pending']);$this->audit($r,'report.'.$item->status,'report:'.$item->id,$v['summary']);if($item->status==='pending')\App\Support\StaffAlerts::role('assistant','New unit report: '.$item->title,'/workspace/report/'.$item->id);return redirect('/workspace/report/'.$item->id)->with('success',$r->boolean('draft')?'Draft saved.':'Report submitted to Assistant.');
 }
 public function reportUpdate(Request $r,Report $report){
  $v=$r->validate(['summary'=>'nullable|string|min:30|max:5000','unit_note'=>'sometimes|nullable|string|max:3000','review_note'=>'nullable|string|min:5|max:3000','status'=>'required|in:pending,draft,awaiting_admin,needs_revision,assistant_revision,approved,rejected','check_content'=>'sometimes|accepted','check_files'=>'sometimes|accepted','check_source'=>'sometimes|accepted','attachments'=>'nullable|array|max:5','attachments.*'=>'file|max:10240|mimes:pdf,png,jpg,jpeg,xlsx,csv,docx']);
  DB::transaction(function()use($r,$report,$v){$q=Report::lockForUpdate()->findOrFail($report->id);$role=$r->user()->role;$previousStatus=$q->status;
   if($q->user_id===$r->user()->id){
    abort_unless(in_array($q->status,['draft','needs_revision'])&&in_array($v['status'],['draft','pending'])&&isset($v['summary']),422);
    $snapshot=$q->snapshot;
    if(!isset($snapshot['records']))$snapshot=['records'=>$snapshot,'version'=>1,'versions'=>[]];
    $snapshot['versions'][]=['version'=>$snapshot['version']??1,'summary'=>$q->summary,'unit_note'=>$snapshot['unit_note']??null,'status'=>$q->status,'files'=>$snapshot['files']??[],'records'=>$snapshot['records'],'saved_at'=>now()->toIso8601String()];
    if(isset($snapshot['date_from'],$snapshot['date_to']))$snapshot=[...$snapshot,...$this->snapshot($r,$snapshot['date_from'],$snapshot['date_to'])];
    if($r->hasFile('attachments')){$files=[];foreach($r->file('attachments') as $f)$files[]=['path'=>$f->store('staff-reports'),'name'=>$f->getClientOriginalName(),'size'=>$f->getSize()];$snapshot['files']=$files;}
    if($role==='slu_head'&&array_key_exists('unit_note',$v))$snapshot['unit_note']=$v['unit_note'];
    $snapshot['version']=($snapshot['version']??1)+1;
    unset($snapshot['admin_review_started_at']);
    $q->update(['summary'=>$v['summary'],'snapshot'=>$snapshot,'status'=>$v['status']]);
   }else{
    $allowed=$role==='assistant'&&in_array($q->status,['pending','assistant_revision'])?['awaiting_admin','needs_revision']:($role==='admin'&&$q->status==='awaiting_admin'?['approved','rejected','assistant_revision']:[]);
    abort_unless(in_array($v['status'],$allowed)&&isset($v['review_note']),422,'Report is not at your review stage.');
    if(in_array($v['status'],['awaiting_admin','approved']))$r->validate(['check_content'=>'required|accepted','check_files'=>'required|accepted','check_source'=>'required|accepted']);
    $q->update(['status'=>$v['status'],'review_note'=>$v['review_note'],'reviewed_by'=>$r->user()->id]);
   }
   $this->audit($r,'report.'.$v['status'],'report:'.$q->id,$v['review_note']??$v['summary']??'');
   $path='/workspace/report/'.$q->id;
   $title='Report update: '.$q->title.' ('.str_replace('_',' ',$v['status']).')';
   match($v['status']){
    'draft'=>null,
    'pending'=>\App\Support\StaffAlerts::role('assistant',$title,$path,$previousStatus==='draft'?'submissions':'resubmitted'),
    'awaiting_admin'=>\App\Support\StaffAlerts::role('admin',$title,$path,$previousStatus==='assistant_revision'?'resubmitted':'submissions'),
    'assistant_revision'=>\App\Support\StaffAlerts::role('assistant',$title,$path,'feedback'),
    default=>\App\Support\StaffAlerts::account($q->user,$title,$path,match($v['status']){
     'needs_revision'=>$role==='assistant'?'report_returned':'feedback',
     'approved'=>'report_acknowledged',
     default=>'feedback',
    },$v['review_note']??''),
   };
  });return back()->with('success','Report saved.');
 }
 public function reportFile(Request $r,Report $report,int $index){abort_unless(in_array($r->user()->role,['admin','assistant'])||$report->user_id===$r->user()->id,403);$snapshot=$report->snapshot;if($r->filled('version')){$version=collect($snapshot['versions']??[])->firstWhere('version',(int)$r->query('version'));abort_unless($version,404);$snapshot=$version;}$f=$snapshot['files'][$index]??null;abort_unless($f&&Storage::exists($f['path']),404);return Storage::download($f['path'],$f['name']);}
 public function reportReceipt(Request $r,Report $report){abort_unless(in_array($r->user()->role,['admin','assistant'])||$report->user_id===$r->user()->id,403);return view('workspace.receipt',['item'=>$report,'history'=>DB::table('audit_logs')->where('subject','report:'.$report->id)->orderBy('id')->get()]);}
 private function organizationLeaderIssue(User $student):?string
 {
  return match(true){
   !$student->hasVerifiedEmail()=>'This student must verify their iServe email before they can lead an organization.',
   (bool)$student->organization_id=>'This student already belongs to an organization.',
   default=>null,
  };
 }
 public function verifyOrganizationLeader(Request $r)
 {
  $this->only($r,'slu_head');
  $reference=trim($r->validate(['student_ref'=>'required|string|max:120'])['student_ref']);
  $students=User::where('account_type','student');
  $matches=(clone $students)->whereRaw('LOWER(TRIM(student_id)) = ?',[Str::lower($reference)])->limit(2)->get();
  if($matches->count()>1){
   return response()->json(['message'=>'More than one account matches that student ID.'],422);
  }
  $student=$matches->first();
  if(!$student&&preg_match('/^Account #(\d+)$/i',$reference,$account)){
   $student=$students->where(fn($query)=>$query->whereNull('student_id')->orWhere('student_id',''))->find($account[1]);
  }
  if(!$student){
   return response()->json(['message'=>'No iServe student account matches that ID.'],404);
  }
  if($message=$this->organizationLeaderIssue($student)){
   return response()->json(['message'=>$message],422);
  }
  return response()->json([
   'id'=>$student->id,'reference'=>$student->student_id ?: 'Account #'.$student->id,
   'name'=>$student->name,'program'=>$student->course,
  ]);
 }
 public function organization(Request $r)
 {
  $this->only($r,'slu_head');
  $draftId=$r->validate(['draft_id'=>'nullable|uuid'])['draft_id']??null;
  StaffDrafts::assertOwned($r->user(),'organization',$draftId);
  $values=$r->validate([
   'name'=>'required|string|max:150|unique:organizations','description'=>'nullable|string|max:3000',
   'president_id'=>'required|integer|exists:users,id','president_ref'=>'nullable|string|max:120','acronym'=>'required|regex:/^[A-Z0-9]{2,10}$/',
   'college'=>'required|string|max:100','type'=>'required|string|max:60','adviser'=>'required|string|max:120',
   'academic_year'=>'nullable|string|max:30','portal_visible'=>'sometimes|boolean',
  ],['president_id.required'=>'Verify an eligible student before creating the organization.']);

  DB::transaction(function()use($r,$values,$draftId){
   $student=User::whereKey($values['president_id'])->lockForUpdate()->firstOrFail();
   $expectedReference=$student->student_id ?: 'Account #'.$student->id;
   $submittedReference=$values['president_ref']??null;
   if ($student->account_type!=='student'||$this->organizationLeaderIssue($student)
    ||($submittedReference!==null&&strcasecmp(trim($submittedReference),trim($expectedReference))!==0)) {
    throw ValidationException::withMessages(['president_id'=>'Select a verified student who is available to lead this organization.']);
   }
   $organization=Organization::create(['name'=>$values['name'],'description'=>$values['description']??null]);
   $student->update(['organization_id'=>$organization->id,'position'=>'President']);
   $this->saveMetadata('organization.'.$organization->id,[
    ...\Illuminate\Support\Arr::only($values,['acronym','college','type','adviser','academic_year']),
    'portal_visible'=>$r->boolean('portal_visible',true),
   ]);
   $this->audit($r,'organization.create',(string)$organization->id);
   StaffDrafts::consume($r->user(),'organization',$draftId);
  },3);
  return back()->with('success','Organization created.');
 }
 public function organizationMember(Request $r,Organization $organization){$this->only($r,'slu_head');$v=$r->validate(['user_id'=>'required|exists:users,id','position'=>'required|in:Member,President,Vice President,Secretary,Treasurer,Auditor,Moderator']);$u=User::findOrFail($v['user_id']);abort_unless($u->account_type==='student'&&(!$u->organization_id||$u->organization_id===$organization->id),422);$u->update(['organization_id'=>$organization->id,'position'=>$v['position']]);$this->audit($r,'organization.member',$organization->name,$u->name.' '.$v['position']);return back()->with('success','Officer/member saved.');}
 public function event(Request $r,Event $event){
  if($event->type==='seminar')return $this->updateSeminar($r,$event);
  $this->only($r,'slu_head');
  $v=$r->validate(['title'=>'required|string|max:150','description'=>'required|string|max:3000','venue'=>'required|string|max:120','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','capacity'=>'required|integer|between:1,5000','status'=>'required|in:published,draft,cancelled,completed']);
  DB::transaction(function()use($r,$event,$v){
   $current=Event::lockForUpdate()->findOrFail($event->id);
   abort_if($v['capacity']<$current->attendances()->count(),422);
   if($v['status']==='completed'){
    abort_unless($current->ends_at->lte(now()),422,'An event cannot be completed before its end time.');
    if(\App\Support\EventScoring::source($current)){
     abort_unless(ServiceRequest::where('kind','activity')->where('details->completion_report',true)->where('details->event_id',$current->id)->where('status','approved')->exists(),422,'Complete the event report approval route first.');
    }
   }
   $previousVenue=$current->venue;
   $current->update($v);
   if($current->wasChanged(['title','venue','starts_at','ends_at','status'])){
    $title=$current->title.($current->status==='cancelled'?' has been cancelled.':' has been updated.');
    $current->attendances()->where('status','<>','cancelled')->with('user')->each(
     fn(Attendance $entry)=>\App\Support\StaffAlerts::account($entry->user,$title,'/portal/leadership/events#event-'.$current->id)
    );
   }
   if($current->type==='leadership'){
    Event::rememberVenues($previousVenue,$current->venue);
    if($r->boolean('custom_venue')&&$previousVenue!==$current->venue)Event::setVenueAvailability($current->venue,true);
   }
   $this->audit($r,'event.update',(string)$current->id);
  });
  return back()->with('success','Event saved.');
 }
 public function removeEventVenue(Request $r){
  $this->only($r,'slu_head');
  $venue=$r->validate(['venue'=>'required|string|max:120'])['venue'];
  abort_unless(Event::venueChoices(Event::where('type','leadership')->distinct()->pluck('venue'))->containsStrict($venue),404);
  DB::transaction(function()use($r,$venue){
   Event::setVenueAvailability($venue,false);
   $this->audit($r,'event.venue.remove',$venue);
  });
  return response()->noContent();
 }
 private function updateSeminar(Request $r,Event $event){
  $this->only($r,'cvu_head');$v=$r->validate(['title'=>'required|string|max:150','description'=>'required|string|max:3000','venue'=>'required|string|max:120','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','capacity'=>'required|integer|between:1,5000','status'=>'required|in:published,draft,cancelled,completed']);
  DB::transaction(function()use($r,$event,$v){
   $e=Event::lockForUpdate()->findOrFail($event->id);$count=$e->attendances()->where('status','<>','cancelled')->count();$this->requireCondition($v['capacity']>=$count,422,'Capacity cannot be below existing registrations.');
   if($v['status']==='completed')$this->requireCondition(\Carbon\Carbon::parse($v['ends_at'])->lte(now()),422,'A seminar cannot be completed before its end time.');
   $changed=$e->title!==$v['title']||$e->venue!==$v['venue']||!$e->starts_at->equalTo(\Carbon\Carbon::parse($v['starts_at']))||!$e->ends_at->equalTo(\Carbon\Carbon::parse($v['ends_at']))||$e->status!==$v['status'];
   $participants=$e->attendances()->where('status','<>','cancelled')->with('user')->get();$e->update($v);
   if($v['status']==='cancelled'){
    $e->attendances()->where('status','pending')->update(['status'=>'cancelled']);
    ServiceRequest::where('kind','seminar')->where('details->event_id',$e->id)->whereNotIn('status',['approved','rejected','cancelled'])->update(['status'=>'cancelled','review_note'=>'The seminar was cancelled by the unit.','reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);
   }
   if($changed){
    $title=$e->title.($e->status==='cancelled'?' has been cancelled.':' has been updated.');
    $path=$e->status==='published'?'/apply/seminar?event='.$e->id:'/apply/seminar';
    foreach($participants as $a)\App\Support\StaffAlerts::account($a->user,$title,$path);
   }
   $this->audit($r,'event.update',(string)$e->id);
  });return back()->with('success','Seminar saved.');
 }
 public function seminar(Request $r){$this->only($r,'cvu_head');$v=$r->validate(['title'=>'required|string|max:150','description'=>'required|string|max:3000','venue'=>'required|string|max:120','starts_at'=>'required|date|after:now','ends_at'=>'required|date|after:starts_at','capacity'=>'required|integer|between:1,5000']);$e=Event::create([...$v,'type'=>'seminar','points'=>0]);$this->audit($r,'seminar.create',(string)$e->id);return back()->with('success','Seminar created.');}
 public function slots(Request $r){$this->only($r,'cvu_head');$v=$r->validate(['date'=>'required|date|after_or_equal:today','start'=>'required|date_format:H:i','end'=>'required|date_format:H:i|after:start','capacity'=>'required|integer|between:1,100','open'=>'nullable|boolean','note'=>'nullable|string|max:300']);$v['open']=$r->boolean('open');DB::transaction(function()use($r,$v){DB::table('settings')->insertOrIgnore(['key'=>'staff.clearance-slots','value'=>'[]']);$row=DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->first();$slots=json_decode($row->value,true)??[];$booked=ServiceRequest::where('kind','clearance')->whereIn('status',['pending','in_review','unit_approved','awaiting_admin','approved'])->where('details->appointment_date',$v['date'])->where('details->schedule',$v['start'])->lockForUpdate()->get()->count();abort_if($booked>$v['capacity'],422,'Capacity cannot be lower than existing bookings.');$slots[$v['date'].' '.$v['start']]=$v;$this->saveMetadata('clearance-slots',$slots);$this->audit($r,'clearance.slot',$v['date'].' '.$v['start']);});return back()->with('success','Availability saved.');}
 public function settings(Request $r)
 {
  $user=$r->user();
  $unitSettings=in_array($user->role,['sfu_head','slu_head'],true);
  $action=$unitSettings?$r->input('save'):null;
  $defaults=['theme'=>'required|in:Light,Dark','date_format'=>'required|in:mm/dd/yyyy,dd/mm/yyyy','time_format'=>'required|in:12,24'];
  $password=['current_password'=>'nullable|required_with:password|current_password','password'=>'nullable|confirmed|min:12'];

  if ($action==='password') {
   $values=$r->validate(['current_password'=>'required|current_password','password'=>'required|string|confirmed|min:12']);
   $user->password=$values['password'];
  } elseif ($action==='defaults') {
   $user->preferences=[...($user->preferences??[]),...$r->validate($defaults)];
  } else {
   $values=$r->validate([...$defaults,...$password,'email'=>'nullable|boolean','in_app'=>'nullable|boolean','two_factor'=>'nullable|boolean','alerts'=>'nullable|array','alerts.*'=>'boolean']);
   if ($r->boolean('two_factor')&&!$user->hasVerifiedEmail()) {
    abort_unless($unitSettings,422);
    throw ValidationException::withMessages(['two_factor'=>'Verify your email before enabling two-step verification.']);
   }
   $alerts=$values['alerts']??[];
   if($user->role==='slu_head')$alerts=array_replace($user->preferences['alerts']??[],$alerts);
   $user->preferences=[...($user->preferences??[]),...array_intersect_key($values,$defaults),
    'email'=>$r->boolean('email'),'in_app'=>$r->boolean('in_app'),'alerts'=>$alerts,
   ];
   $user->two_factor=$r->boolean('two_factor');
   if ($r->filled('password')) $user->password=$values['password'];
  }

  $user->save();
  $this->audit($r,'account.settings',$user->email);
  return back()->with('success',match($action){'password'=>'Password changed.','defaults'=>'Unit defaults saved.',default=>'Settings saved.'});
 }
 public function backup(Request $r){$role=$r->user()->role;$data=['created_at'=>now()->toIso8601String(),'unit'=>$role,'requests'=>$this->scope($r)->get()->map(fn($q)=>$q->only(['ticket','kind','status','details','created_at'])),'reports'=>$this->reports($r)->get()->map(fn($q)=>$q->only(['id','title','summary','snapshot','status','created_at']))];$this->audit($r,'backup.export',$role);return response()->streamDownload(fn()=>print(json_encode($data,JSON_PRETTY_PRINT)),'iServe-'.$role.'-'.now()->format('Ymd-His').'.json',['Content-Type'=>'application/json']);}
 public function rules(Request $r){
  $this->only($r,'slu_head');
  $v=$r->validate(['event'=>'required|integer|between:0,1000','achievement'=>'required|integer|between:0,1000','report'=>'required|integer|between:0,1000','participant'=>'required|integer|between:0,1000','penalty'=>'nullable|integer|between:0,1000']);
  $v['penalty']=$v['penalty']??0;
  DB::transaction(function()use($r,$v){
   $this->saveMetadata('points-rules',$v);
   $this->audit($r,'points.rules','SLU',json_encode($v));
  });
  return back()->with('success','Scoring rules saved.')->with('scoring_rules_saved',true);
 }
 public function assignment(Request $r){$this->only($r,'cvu_head');\App\Support\StaffDrafts::assertOwned($r->user(),'assignment',$r->input('draft_id'));$schedule=$r->validate(['start_date'=>'required|date','target_date'=>'required|date|after_or_equal:start_date','duty_start'=>'required|date_format:H:i','duty_end'=>'required|date_format:H:i|after:duty_start']);$r->merge(['note'=>trim(($r->note??'')."\nStart: ".$schedule['start_date']."; Target: ".$schedule['target_date']."; Duty: ".$schedule['duty_start'].' - '.$schedule['duty_end'])]);$response=app(StaffController::class)->assignment($r);\App\Support\StaffDrafts::consume($r->user(),'assignment',$r->input('draft_id'));return $response;}
 public function communityServiceHours(Request $request)
 {
  $this->only($request,'cvu_head');
  $limits=$request->validate([
   'Minor.min'=>'required|integer|between:1,500',
   'Minor.max'=>'required|integer|between:1,500|gte:Minor.min',
   'Major.min'=>'required|integer|between:1,500',
   'Major.max'=>'required|integer|between:1,500|gte:Major.min',
  ],[
   'Minor.max.gte'=>'The minor maximum must be at least the minor minimum.',
   'Major.max.gte'=>'The major maximum must be at least the major minimum.',
  ]);
  DB::transaction(function()use($request,$limits){
   $this->saveMetadata(CommunityServiceHours::SETTING,$limits);
   $this->audit($request,'assignment.hour_limits','CVU',json_encode($limits));
  });
  return back()->with('success','Community service hour limits saved.');
 }
 public function assignmentUpdate(Request $r,Assignment $assignment)
 {
  $this->only($r,'cvu_head');
  $v=$r->validate(['department'=>'required|string|max:120','supervisor'=>'required|string|max:120','required_hours'=>'required|integer|between:1,500','note'=>'nullable|string|max:3000']);
  DB::transaction(function()use($r,$assignment,$v){
   $current=Assignment::lockForUpdate()->findOrFail($assignment->id);
   $minutes=(int)$v['required_hours']*60;
   abort_unless($current->status==='active',422);
   if($minutes!==(int)$current->required_minutes)CommunityServiceHours::validate($current->severity,(int)$v['required_hours']);
   if($minutes<$current->approvedMinutes()){
    throw ValidationException::withMessages(['required_hours'=>'Required hours cannot be less than the hours already verified.']);
   }
   $current->update(['department'=>$v['department'],'supervisor'=>$v['supervisor'],'required_minutes'=>$minutes,'note'=>$v['note']??null]);
   $this->audit($r,'assignment.update',$current->record_number);
  });
  return back()->with('success','Assignment saved.');
 }
 public function assignmentFeedback(Request $request, Assignment $assignment)
 {
  $this->only($request,'cvu_head');
  $feedback=$request->validate(['feedback'=>'required|string|max:3000']);
  DB::transaction(function()use($request,$assignment,$feedback){
   $assignment->feedbackEntries()->create(['author_id'=>$request->user()->id,'message'=>$feedback['feedback']]);
   $this->audit($request,'assignment.feedback_posted',$assignment->record_number);
   \App\Support\StaffAlerts::account($assignment->user,'New community service feedback: '.$assignment->record_number,
    '/portal/values/community-service?assignment='.$assignment->id,message:'Open your service record to read the CVU Head’s feedback.');
  });
  return back()->with('success','Feedback saved.')->with('community_feedback_assignment',$assignment->id);
 }
 public function content(Request $r,?int $id=null){
  $this->only($r,'slu_head');$v=$r->validate(['title'=>'required|string|max:180','body'=>'required|string|max:10000','publish'=>'nullable|boolean','published_at'=>'nullable|date','ends_at'=>'nullable|date|after:published_at','content_type'=>'required|in:Announcement Banner,Featured Event,Organization Feature,Notification,Helping Content','portal_location'=>'required|in:Student Dashboard,Event & Activities,Achievement Form','audience'=>'required|in:All Students,All Student Leaders','cta_label'=>'nullable|string|max:80','cta_destination'=>'nullable|in:events,organizations,proposals,achievements','image'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:5120']);
  if($id)abort_unless(DB::table('announcements')->where('id',$id)->where('unit','leadership')->exists(),404);
  $data=['title'=>$v['title'],'body'=>$v['body'],'published_at'=>$r->boolean('publish')?($v['published_at']??now()):null,'updated_at'=>now()];
  if($id)DB::table('announcements')->where('id',$id)->update($data);else $id=DB::table('announcements')->insertGetId([...$data,'user_id'=>$r->user()->id,'unit'=>'leadership','created_at'=>now()]);
  $meta=$this->metadata('content.'.$id);foreach(['ends_at','content_type','portal_location','audience','cta_label','cta_destination'] as $key)$meta[$key]=$v[$key]??null;
  if($r->hasFile('image')){$file=$r->file('image');$meta['image']=['path'=>$file->store('content'),'name'=>$file->getClientOriginalName()];}
  $this->saveMetadata('content.'.$id,$meta);$this->audit($r,'content.saved',(string)$id);return back()->with('success','Portal content saved.');
 }
 public function scholar(Request $r)
 {
  $this->only($r,'sfu_head');
  $savingDraft=$r->input('intent')==='draft';
  $studentIdRule=$savingDraft?'nullable':'required_without:user_id';
  $values=$r->validate([
   'draft_id'=>'nullable|uuid','user_id'=>'nullable|integer|exists:users,id',
   'student_id'=>$studentIdRule.'|string|max:40','full_name'=>'nullable|string|max:120',
   'course'=>'nullable|string|max:100','year_level'=>'nullable|string|max:30',
   'academic_year'=>'nullable|string|max:30','semester'=>'nullable|in:1st Semester,2nd Semester,Summer',
   'application'=>'nullable|file|max:5120|mimes:pdf,png,jpg,jpeg',
   'photo'=>'nullable|image|max:5120|mimes:png,jpg,jpeg',
   'registration'=>'nullable|file|max:5120|mimes:pdf,png,jpg,jpeg',
  ]);
  $draftId=$values['draft_id']??null;
  $draftKey=StaffDrafts::prefix($r->user(),'scholar').($draftId??(string) Str::uuid());
  $fields=\Illuminate\Support\Arr::only($values,['full_name','student_id','course','year_level','academic_year','semester']);
  $documents=config('portal.documents.scholarship');
  $uploads=[];

  try {
   foreach ($r->only(array_keys($documents)) as $key=>$file) {
    if (!$r->hasFile($key)) continue;
    $path=$file->store('requests');
    if (!$path) throw ValidationException::withMessages([$key=>'The file could not be saved. Try uploading it again.']);
    $uploads[$key]=['path'=>$path,'name'=>$file->getClientOriginalName(),'size'=>$file->getSize()];
   }

   $replaced=DB::transaction(function()use($r,$values,$savingDraft,$draftId,$draftKey,$fields,$documents,$uploads){
    $stored=$draftId?DB::table('settings')->where('key',$draftKey)->lockForUpdate()->first():null;
    abort_if($draftId&&!$stored,404);
    $priorFiles=$stored?(json_decode($stored->value,true)['files']??[]):[];
    $files=array_replace($priorFiles,$uploads);

    if ($savingDraft) {
     if (!array_filter($fields)&&!$files) throw ValidationException::withMessages(['student_id'=>'Enter some details or attach a document before saving a draft.']);
     DB::table('settings')->updateOrInsert(['key'=>$draftKey],['value'=>json_encode([
      'id'=>substr($draftKey,-36),'fields'=>$fields,'files'=>$files,'saved_at'=>now()->toIso8601String(),
     ],JSON_THROW_ON_ERROR)]);
    } else {
     $student=User::where('account_type','student')
      ->when(isset($values['user_id']),fn($query)=>$query->whereKey($values['user_id']))
      ->when(isset($values['student_id']),fn($query)=>$query->where('student_id',$values['student_id']))
      ->lockForUpdate()->first();
     if (!$student) throw ValidationException::withMessages(['student_id'=>'Enter the ID of an existing student account.']);
     $missing=array_diff_key($documents,$files);
     if ($missing) throw ValidationException::withMessages(array_map(fn($label)=>'Upload '.$label.'.',$missing));
     if ($student->requests()->where('kind','scholarship')->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin'])->exists()) {
      throw ValidationException::withMessages(['student_id'=>'This student already has an open application.']);
     }
     $term=DB::table('settings')->whereIn('key',['academic_year','semester'])->pluck('value','key');
     $application=$student->requests()->create([
      'ticket'=>'TFD-'.now()->format('Y').'-'.strtoupper(Str::random(8)),'kind'=>'scholarship','files'=>$files,
      'details'=>[
       'full_name'=>$fields['full_name']??$student->name,'student_id'=>$student->student_id,
       'course'=>$fields['course']??$student->course,'year_level'=>$fields['year_level']??$student->year_level,
       'academic_year'=>$fields['academic_year']??$term['academic_year']??now()->year.'–'.(now()->year+1),
       'semester'=>$fields['semester']??$term['semester']??'1st Semester',
       'reason'=>'Application recorded by SFU Head on behalf of student.',
      ],
     ]);
     $this->audit($r,'scholar.recorded',$application->ticket);
     StaffDrafts::consume($r->user(),'scholar',$draftId);
    }

    return array_column(array_intersect_key($priorFiles,$uploads),'path');
   },3);
  } catch (\Throwable $error) {
   Storage::delete(array_column($uploads,'path'));
   throw $error;
  }

  Storage::delete($replaced);
  return back()->with('success',$savingDraft?'Scholar application draft saved.':'Application recorded for unit review.');
 }
 public function adjustment(Request $r){$this->only($r,'slu_head');$v=$r->validate(['user_id'=>'required|exists:users,id','amount'=>'required|integer|between:-1000,1000|not_in:0','reason'=>'required|string|min:10|max:3000']);$u=User::findOrFail($v['user_id']);abort_unless($u->account_type==='student'&&$u->organization_id,422);$q=ServiceRequest::create(['user_id'=>$u->id,'ticket'=>'ADJ-'.now()->format('Y').'-'.strtoupper(Str::random(8)),'kind'=>'achievement','status'=>'unit_approved','details'=>['title'=>'Point Adjustment','description'=>$v['reason'],'awarded_points'=>$v['amount'],'adjustment'=>true],'review_note'=>$v['reason'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);$this->audit($r,'request.unit_approved',$q->ticket,$v['reason']);return back()->with('success','Point adjustment sent to Assistant for forwarding. Points apply after final Admin approval.');}
 public function disposition(Request $r,ServiceRequest $serviceRequest)
 {
  abort_unless($r->user()->role===$serviceRequest->office(),403);
  $v=$r->validate(['action'=>'required|in:archive,restore,release,reprint','review_note'=>'required_unless:action,reprint|nullable|string|min:5|max:3000']);
  DB::transaction(function()use($r,$serviceRequest,$v){
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
   if($v['action']==='reprint'){
    if($q->kind==='id-replacement'&&filled($q->details['reprinted_at']??null))return;
    $this->requireCondition($q->canMarkIdReprinted(),422,'Only approved ID replacements awaiting release can be marked as reprinted.');
    $q->update(['details'=>[...$q->details,'reprinted_at'=>now()->toIso8601String()]]);
    $this->audit($r,'request.reprinted',$q->ticket);
    $title='Replacement ID reprinted: '.$q->ticket;
    $message='Your replacement ID has been reprinted. Contact the CVU office for pickup.';
    $url=rtrim(config('app.student_portal_url'),'/').'/requests/'.$q->id;
    if($q->user->preferences['in_app']??true)$q->user->notifications()->create([
     'id'=>Str::uuid(),'type'=>'request','data'=>['title'=>$title,'url'=>$url,'message'=>$message],
    ]);
    \App\Support\TransactionEmail::sendAfterCommit($q->user,'iServe '.$title,$message."\n\nView your request: ".$url,['request_id'=>$q->id]);
    return;
   }

   abort_unless(in_array($q->status,['approved','rejected','cancelled']),422);
   $details=$q->details;
   if($v['action']==='release'){
    abort_unless($q->status==='approved'&&in_array($q->kind,['lost','found','id-replacement']),422);
    $details['released']=true;
    $details['released_at']=now()->toIso8601String();
   }else{
    $details['archived']=$v['action']==='archive';
   }
   $q->update(['details'=>$details]);
   $this->audit($r,'request.'.$v['action'],$q->ticket,$v['review_note']);
  });
  return back()->with('success',$v['action']==='reprint'?'Replacement ID marked as reprinted.':'Record updated.');
 }
}
