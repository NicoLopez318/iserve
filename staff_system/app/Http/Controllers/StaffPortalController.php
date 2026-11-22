<?php
namespace App\Http\Controllers;

use App\Models\{User,ServiceRequest,Report,Organization,Event,Assignment,Attendance,Journal,Point};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,Hash};
use Illuminate\Support\Str;

class StaffPortalController extends Controller
{
 public static function menus(string $role):array {
  return match($role){
   'cvu_head'=>['dashboard'=>'Dashboard','complaints'=>'Complaints & Concerns','lost-found'=>'Lost & Found','readmission'=>'Re-Admission','id-replacement'=>'ID Replacement','community'=>'Community Service','seminars'=>'Seminar','clearance'=>'Clearance','reports'=>'Reports','settings'=>'Settings'],
   'sfu_head'=>['dashboard'=>'Dashboard','applications'=>'Discount Applications','reports'=>'Reports','settings'=>'Settings'],
   'slu_head'=>['dashboard'=>'Dashboard','organizations'=>'Organizations','proposals'=>'Proposals','events'=>'Events & Activities','achievements'=>'Achievements','points'=>'Leaderboards & Points','reports'=>'Reports','content'=>'Portal Content','settings'=>'Settings'],
   'assistant'=>['dashboard'=>'Dashboard','inbox'=>'Report Inbox','leadership'=>'Student Leadership','scholarship'=>'Scholarship & Financial','values'=>'Character & Values','history'=>'Submission History','settings'=>'Settings'],
   'admin'=>['dashboard'=>'Dashboard','inbox'=>'Report for Review','leadership'=>'Student Leadership','scholarship'=>'Scholarship & Financial','values'=>'Character & Values','history'=>'Review History','settings'=>'Settings'],
   default=>[],
  };
 }
 private function scope(Request $r){return ServiceRequest::with(['user','reviewer'])->when(!in_array($r->user()->role,['admin','assistant']),fn($q)=>$q->whereIn('kind',config('portal.office.'.$r->user()->role,[])));}
 private function reports(Request $r){return Report::with('user')->when(!in_array($r->user()->role,['admin','assistant']),fn($q)=>$q->where('user_id',$r->user()->id));}
 private function audit(Request $r,string $action,string $subject,string $details=''){DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>$action,'subject'=>$subject,'details'=>$details,'created_at'=>now()]);}
 private function unitRole(?string $unit):?string{return ['leadership'=>'slu_head','scholarship'=>'sfu_head','values'=>'cvu_head'][$unit]??null;}
 private function only(Request $r,string $role):void{abort_unless($r->user()->role===$role,403);}
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
  $scope=$this->scope($r);$kinds=['complaints'=>['complaint'],'lost-found'=>['lost','found'],'readmission'=>['readmission'],'id-replacement'=>['id-replacement'],'applications'=>['scholarship'],'organizations'=>['leadership'],'proposals'=>['activity','leadership'],'achievements'=>['achievement'],'clearance'=>['clearance'],'seminars'=>['seminar']];
  if(isset($kinds[$page]))$scope->whereIn('kind',$kinds[$page]);
  if($unit=$this->unitRole($page))$scope->whereIn('kind',config('portal.office.'.$unit));
  $scope->when($r->filled('search'),fn($q)=>$q->where(fn($x)=>$x->where('ticket','like','%'.$r->search.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$r->search.'%')->orWhere('student_id','like','%'.$r->search.'%'))));
  $scope->when($r->filled('status'),fn($q)=>$q->where('status',$r->status));
  if($page==='points')$scope->where('details->adjustment',true)->whereIn('status',['unit_approved','awaiting_admin']);
  $scope->when($r->boolean('archive'),fn($q)=>$q->where('details->archived',true),fn($q)=>$q->where(fn($x)=>$x->whereNull('details->archived')->orWhere('details->archived',false)));
  $requests=$scope->latest()->paginate(5,['*'],'transactions')->withQueryString();
  $reports=$this->reports($r)->when($unit,fn($q)=>$q->whereHas('user',fn($u)=>$u->where('role',$unit)))->when($r->filled('search'),fn($q)=>$q->where('title','like','%'.$r->search.'%'))->when($r->filled('status'),fn($q)=>$q->where('status',$r->status))->latest()->paginate(in_array($r->user()->role,['admin','assistant'])?4:5,['*'],'reports')->withQueryString();
  $counts=$this->scope($r)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status');
  $reportCounts=$this->reports($r)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status');
  $organizations=Organization::withCount('members')->get();$events=Event::withCount(['attendances'=>fn($q)=>$q->when($page==='seminars',fn($x)=>$x->where('status','<>','cancelled'))])->latest()->get();
  $eventVenues=$page==='events'
   ?Event::venueChoices($events->where('type','leadership')->pluck('venue'))
   :collect();
  $assignments=Assignment::with(['user','attendances','journals'])->latest()->get();$students=User::where('account_type','student')->get();
  $attendance=Attendance::with(['user','event','assignment'])->where('status','pending')->get();$journals=Journal::with('assignment.user')->where('status','pending')->get();
  $points=Point::with('user')->latest()->get();$rankings=$organizations->map(fn($o)=>['organization'=>$o,'total'=>Point::whereIn('user_id',$o->members()->pluck('id'))->sum('amount')])->sortByDesc('total')->values();
  $audit=DB::table('audit_logs')->when(!in_array($r->user()->role,['admin','assistant']),fn($q)=>$q->where('user_id',$r->user()->id))->latest('id')->limit(80)->get();
  $announcements=DB::table('announcements')->where('unit','leadership')->latest('id')->get();$slots=array_map(fn($slot)=>[...$slot,'end'=>$slot['end']??'—'],$this->metadata('clearance-slots'));$rules=$this->metadata('points-rules');
  $designs=json_decode(file_get_contents(base_path('design-pages.json')),true);$design=$designs[$r->user()->role][$page]??['title'=>$menus[$page],'description'=>''];foreach($design['cards']??[] as $i=>$card)$design['cards'][$i]['value']=$this->cardValue($r,$card['label'],$page);
  $selectedRequest=null;$selectedHistory=collect();
  if(!$r->filled('report')&&$r->filled('review')){$r->validate(['review'=>'integer|min:1']);$selectedRequest=$this->scope($r)->findOrFail($r->integer('review'));}
  elseif($r->user()->role==='sfu_head'&&in_array($page,['dashboard','applications']))$selectedRequest=$requests->first();
  if($selectedRequest){$selectedRequest->load(['user','reviewer']);$selectedHistory=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject',$selectedRequest->ticket)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();}
  $selectedReport=null;$reportHistory=collect();if($r->filled('report')){$r->validate(['report'=>'integer|min:1']);$selectedReport=$this->reports($r)->findOrFail($r->integer('report'));$reportHistory=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject','report:'.$selectedReport->id)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();}
  return view('workspace.page',compact('selectedReport','reportHistory','selectedRequest','selectedHistory','page','menus','design','requests','reports','counts','reportCounts','organizations','events','eventVenues','assignments','students','attendance','journals','points','rankings','audit','announcements','slots','rules'));
 }
 public function request(Request $r,ServiceRequest $serviceRequest){
  abort_unless(in_array($r->user()->role,['admin','assistant'])||$serviceRequest->office()===$r->user()->role,403);
  $serviceRequest->load(['user','reviewer']);$history=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject',$serviceRequest->ticket)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();
  return view('workspace.request',['item'=>$serviceRequest,'history'=>$history,'menus'=>self::menus($r->user()->role)]);
 }
 public function requestNote(Request $r,ServiceRequest $serviceRequest){
  abort_unless($r->user()->role===$serviceRequest->office()&&in_array($serviceRequest->status,['pending','in_review']),403);
  $v=$r->validate(['review_note'=>'required|string|min:5|max:3000','interview_at'=>'nullable|date|after:now','appointment_date'=>'nullable|date','schedule'=>'nullable|date_format:H:i','identity_verified'=>'nullable|boolean','hymn_completed'=>'nullable|boolean']);
  DB::transaction(function()use($r,$serviceRequest,$v){
   if($serviceRequest->kind==='clearance'){DB::table('settings')->insertOrIgnore(['key'=>'staff.clearance-slots','value'=>'[]']);DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->first();}
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);$this->requireCondition(in_array($q->status,['pending','in_review']),422);$details=$q->details;
   if($q->kind==='clearance'&&(isset($v['appointment_date'])||isset($v['schedule']))){
    $date=$v['appointment_date']??$details['appointment_date'];$time=$v['schedule']??$details['schedule'];$key=$date.' '.$time;$changed=$key!==(($details['appointment_date']??'').' '.($details['schedule']??''));
    $slots=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);$slot=$slots[$key]??null;
    if($changed){$this->requireCondition(\Carbon\Carbon::parse($key)->isFuture()&&$slot&&($slot['open']??false),422,'Choose an open future appointment.');$booked=ServiceRequest::where('kind','clearance')->where('id','!=',$q->id)->whereNotIn('status',['rejected','cancelled'])->where('details->appointment_date',$date)->where('details->schedule',$time)->count();$this->requireCondition($booked<(int)$slot['capacity'],422,'This appointment slot is full.');}
    $details['appointment_date']=$date;$details['schedule']=$time;$details['appointment_slot']=$key;$details['appointment_status']='confirmed';
   }
   foreach(['interview_at','identity_verified','hymn_completed'] as $key)if(array_key_exists($key,$v))$details[$key]=$v[$key];
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
 public function slip(Request $r,ServiceRequest $serviceRequest){abort_unless((in_array($r->user()->role,['admin','assistant'])||$r->user()->role===$serviceRequest->office())&&$serviceRequest->status==='approved',403);return view('workspace.slip',['item'=>$serviceRequest]);}
 public function report(Request $r,Report $report){abort_unless(in_array($r->user()->role,['admin','assistant'])||$report->user_id===$r->user()->id,403);$report->load('user');$history=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('subject','report:'.$report->id)->select('audit_logs.*','users.name')->orderBy('audit_logs.id')->get();return view('workspace.report',['item'=>$report,'history'=>$history,'menus'=>self::menus($r->user()->role)]);}
 public function reportStart(Request $r,Report $report){$this->only($r,'admin');DB::transaction(function()use($r,$report){$q=Report::lockForUpdate()->findOrFail($report->id);abort_unless($q->status==='awaiting_admin',422);$snapshot=$q->snapshot;if(!isset($snapshot['records']))$snapshot=['records'=>$snapshot,'version'=>1];if(!isset($snapshot['admin_review_started_at'])){$snapshot['admin_review_started_at']=now()->toIso8601String();$q->update(['snapshot'=>$snapshot]);$this->audit($r,'report.review_started','report:'.$q->id);}});return redirect(isset(self::menus($r->user()->role)[$r->input('page')??''])?'/workspace/'.$r->input('page').'?report='.$report->id:'/workspace/report/'.$report->id);}
 public function reportStore(Request $r){
  abort_unless(in_array($r->user()->role,['cvu_head','slu_head','sfu_head']),403);$v=$r->validate(['title'=>'required|string|max:150','academic_year'=>'required|string|max:30','semester'=>'required|in:1st Semester,2nd Semester,Summer','summary'=>'required|string|min:30|max:5000','date_from'=>'required|date','date_to'=>'required|date|after_or_equal:date_from','draft'=>'nullable|boolean','attachments'=>'nullable|array|max:5','attachments.*'=>'file|max:10240|mimes:pdf,png,jpg,jpeg,xlsx,csv,docx']);
  $snapshot=$this->snapshot($r,$v['date_from'],$v['date_to']);
  $files=[];foreach($r->file('attachments',[]) as $f)$files[]=['path'=>$f->store('staff-reports'),'name'=>$f->getClientOriginalName(),'size'=>$f->getSize()];
  $item=Report::create(['user_id'=>$r->user()->id,'title'=>$v['title'],'academic_year'=>$v['academic_year'],'semester'=>$v['semester'],'summary'=>$v['summary'],'snapshot'=>[...$snapshot,'files'=>$files,'version'=>1,'versions'=>[]],'status'=>$r->boolean('draft')?'draft':'pending']);$this->audit($r,'report.'.$item->status,'report:'.$item->id,$v['summary']);if($item->status==='pending')\App\Support\StaffAlerts::role('assistant','New unit report: '.$item->title,'/workspace/report/'.$item->id);return redirect('/workspace/report/'.$item->id)->with('success',$r->boolean('draft')?'Draft saved.':'Report submitted to Assistant.');
 }
 public function reportUpdate(Request $r,Report $report){
  $v=$r->validate(['summary'=>'nullable|string|min:30|max:5000','review_note'=>'nullable|string|min:5|max:3000','status'=>'required|in:pending,draft,awaiting_admin,needs_revision,assistant_revision,approved,rejected','check_content'=>'sometimes|accepted','check_files'=>'sometimes|accepted','check_source'=>'sometimes|accepted','attachments'=>'nullable|array|max:5','attachments.*'=>'file|max:10240|mimes:pdf,png,jpg,jpeg,xlsx,csv,docx']);
  DB::transaction(function()use($r,$report,$v){$q=Report::lockForUpdate()->findOrFail($report->id);$role=$r->user()->role;$previousStatus=$q->status;
   if($q->user_id===$r->user()->id){abort_unless(in_array($q->status,['draft','needs_revision'])&&in_array($v['status'],['draft','pending'])&&isset($v['summary']),422);$snapshot=$q->snapshot;if(!isset($snapshot['records']))$snapshot=['records'=>$snapshot,'version'=>1,'versions'=>[]];$snapshot['versions'][]=['version'=>$snapshot['version']??1,'summary'=>$q->summary,'status'=>$q->status,'files'=>$snapshot['files']??[],'records'=>$snapshot['records'],'saved_at'=>now()->toIso8601String()];if(isset($snapshot['date_from'],$snapshot['date_to']))$snapshot=[...$snapshot,...$this->snapshot($r,$snapshot['date_from'],$snapshot['date_to'])];if($r->hasFile('attachments')){$files=[];foreach($r->file('attachments') as $f)$files[]=['path'=>$f->store('staff-reports'),'name'=>$f->getClientOriginalName(),'size'=>$f->getSize()];$snapshot['files']=$files;}$snapshot['version']=($snapshot['version']??1)+1;unset($snapshot['admin_review_started_at']);$q->update(['summary'=>$v['summary'],'snapshot'=>$snapshot,'status'=>$v['status']]);
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
    default=>\App\Support\StaffAlerts::account($q->user,$title,$path,message:$v['review_note']??''),
   };
  });return back()->with('success','Report saved.');
 }
 public function reportFile(Request $r,Report $report,int $index){abort_unless(in_array($r->user()->role,['admin','assistant'])||$report->user_id===$r->user()->id,403);$snapshot=$report->snapshot;if($r->filled('version')){$version=collect($snapshot['versions']??[])->firstWhere('version',(int)$r->query('version'));abort_unless($version,404);$snapshot=$version;}$f=$snapshot['files'][$index]??null;abort_unless($f&&Storage::exists($f['path']),404);return Storage::download($f['path'],$f['name']);}
 public function reportReceipt(Request $r,Report $report){abort_unless(in_array($r->user()->role,['admin','assistant'])||$report->user_id===$r->user()->id,403);return view('workspace.receipt',['item'=>$report,'history'=>DB::table('audit_logs')->where('subject','report:'.$report->id)->orderBy('id')->get()]);}
 public function organization(Request $r){$this->only($r,'slu_head');\App\Support\StaffDrafts::assertOwned($r->user(),'organization',$r->input('draft_id'));$v=$r->validate(['name'=>'required|string|max:150|unique:organizations','description'=>'required|string|max:3000','president_id'=>'required|exists:users,id','acronym'=>'required|regex:/^[A-Z0-9]{2,10}$/','college'=>'required|string|max:100','type'=>'required|string|max:60','adviser'=>'required|string|max:120']);$student=User::findOrFail($v['president_id']);abort_unless($student->account_type==='student'&&$student->hasVerifiedEmail()&&!$student->organization_id,422);DB::transaction(function()use($r,$v,$student){$o=Organization::create(['name'=>$v['name'],'description'=>$v['description']]);$student->update(['organization_id'=>$o->id,'position'=>'President']);$this->saveMetadata('organization.'.$o->id,array_intersect_key($v,array_flip(['acronym','college','type','adviser'])));$this->audit($r,'organization.create',(string)$o->id);\App\Support\StaffDrafts::consume($r->user(),'organization',$r->input('draft_id'));});return back()->with('success','Organization created.');}
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
 public function settings(Request $r){$v=$r->validate(['theme'=>'required|in:Light,Dark','date_format'=>'required|in:mm/dd/yyyy,dd/mm/yyyy','time_format'=>'required|in:12,24','email'=>'nullable|boolean','in_app'=>'nullable|boolean','two_factor'=>'nullable|boolean','alerts'=>'nullable|array','alerts.*'=>'boolean','current_password'=>'nullable|required_with:password|current_password','password'=>'nullable|confirmed|min:12']);abort_if($r->boolean('two_factor')&&!$r->user()->hasVerifiedEmail(),422);$u=$r->user();$u->preferences=[...($u->preferences??[]),...array_intersect_key($v,array_flip(['theme','date_format','time_format'])),'email'=>$r->boolean('email'),'in_app'=>$r->boolean('in_app'),'alerts'=>$v['alerts']??[]];$u->two_factor=$r->boolean('two_factor');if($r->filled('password'))$u->password=$v['password'];$u->save();$this->audit($r,'account.settings',$u->email);return back()->with('success','Settings saved.');}
 public function backup(Request $r){$role=$r->user()->role;$data=['created_at'=>now()->toIso8601String(),'unit'=>$role,'requests'=>$this->scope($r)->get()->map(fn($q)=>$q->only(['ticket','kind','status','details','created_at'])),'reports'=>$this->reports($r)->get()->map(fn($q)=>$q->only(['id','title','summary','snapshot','status','created_at']))];$this->audit($r,'backup.export',$role);return response()->streamDownload(fn()=>print(json_encode($data,JSON_PRETTY_PRINT)),'iServe-'.$role.'-'.now()->format('Ymd-His').'.json',['Content-Type'=>'application/json']);}
 public function rules(Request $r){$this->only($r,'slu_head');$v=$r->validate(['event'=>'required|integer|between:0,1000','achievement'=>'required|integer|between:0,1000','report'=>'required|integer|between:0,1000','participant'=>'required|integer|between:0,1000','penalty'=>'nullable|integer|between:0,1000']);$v['penalty']=$v['penalty']??0;$this->saveMetadata('points-rules',$v);$this->audit($r,'points.rules','SLU',json_encode($v));return back()->with('success','Scoring rules saved.');}
 public function assignment(Request $r){$this->only($r,'cvu_head');\App\Support\StaffDrafts::assertOwned($r->user(),'assignment',$r->input('draft_id'));$schedule=$r->validate(['start_date'=>'required|date','target_date'=>'required|date|after_or_equal:start_date','duty_start'=>'required|date_format:H:i','duty_end'=>'required|date_format:H:i|after:duty_start']);$r->merge(['note'=>trim(($r->note??'')."\nStart: ".$schedule['start_date']."; Target: ".$schedule['target_date']."; Duty: ".$schedule['duty_start'].' - '.$schedule['duty_end'])]);$response=app(StaffController::class)->assignment($r);\App\Support\StaffDrafts::consume($r->user(),'assignment',$r->input('draft_id'));return $response;}
 public function assignmentUpdate(Request $r,Assignment $assignment){$this->only($r,'cvu_head');$v=$r->validate(['department'=>'required|string|max:120','supervisor'=>'required|string|max:120','required_hours'=>'required|integer|between:1,500','note'=>'nullable|string|max:3000']);abort_unless($assignment->status==='active'&&$v['required_hours']*60>=$assignment->approvedMinutes(),422);$assignment->update(['department'=>$v['department'],'supervisor'=>$v['supervisor'],'required_minutes'=>$v['required_hours']*60,'note'=>$v['note']??null]);$this->audit($r,'assignment.update',$assignment->record_number);return back()->with('success','Assignment saved.');}
 public function content(Request $r,?int $id=null){
  $this->only($r,'slu_head');$v=$r->validate(['title'=>'required|string|max:180','body'=>'required|string|max:10000','publish'=>'nullable|boolean','published_at'=>'nullable|date','ends_at'=>'nullable|date|after:published_at','content_type'=>'required|in:Announcement Banner,Featured Event,Organization Feature,Notification,Helping Content','portal_location'=>'required|in:Student Dashboard,Event & Activities,Achievement Form','audience'=>'required|in:All Students,All Student Leaders','cta_label'=>'nullable|string|max:80','cta_destination'=>'nullable|in:events,organizations,proposals,achievements','image'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:5120']);
  if($id)abort_unless(DB::table('announcements')->where('id',$id)->where('unit','leadership')->exists(),404);
  $data=['title'=>$v['title'],'body'=>$v['body'],'published_at'=>$r->boolean('publish')?($v['published_at']??now()):null,'updated_at'=>now()];
  if($id)DB::table('announcements')->where('id',$id)->update($data);else $id=DB::table('announcements')->insertGetId([...$data,'user_id'=>$r->user()->id,'unit'=>'leadership','created_at'=>now()]);
  $meta=$this->metadata('content.'.$id);foreach(['ends_at','content_type','portal_location','audience','cta_label','cta_destination'] as $key)$meta[$key]=$v[$key]??null;
  if($r->hasFile('image')){$file=$r->file('image');$meta['image']=['path'=>$file->store('content'),'name'=>$file->getClientOriginalName()];}
  $this->saveMetadata('content.'.$id,$meta);$this->audit($r,'content.saved',(string)$id);return back()->with('success','Portal content saved.');
 }
 public function scholar(Request $r){$this->only($r,'sfu_head');$v=$r->validate(['user_id'=>'required|exists:users,id','academic_year'=>'required|string|max:30','semester'=>'required|in:1st Semester,2nd Semester,Summer','application'=>'required|file|max:5120|mimes:pdf,png,jpg,jpeg','photo'=>'required|image|max:5120','registration'=>'required|file|max:5120|mimes:pdf,png,jpg,jpeg']);$u=User::findOrFail($v['user_id']);abort_unless($u->account_type==='student',422);DB::transaction(function()use($r,$v,$u){User::where('id',$u->id)->lockForUpdate()->first();abort_if(ServiceRequest::where('user_id',$u->id)->where('kind','scholarship')->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin'])->exists(),422,'Student already has an open application.');$files=[];foreach(['application','photo','registration'] as $key){$f=$r->file($key);$files[$key]=['path'=>$f->store('requests'),'name'=>$f->getClientOriginalName(),'size'=>$f->getSize()];}$q=ServiceRequest::create(['user_id'=>$u->id,'ticket'=>'TFD-'.now()->format('Y').'-'.strtoupper(Str::random(8)),'kind'=>'scholarship','details'=>['academic_year'=>$v['academic_year'],'semester'=>$v['semester'],'reason'=>'Application recorded by SFU Head on behalf of student.'],'files'=>$files]);$this->audit($r,'scholar.recorded',$q->ticket);});return back()->with('success','Application recorded for unit review.');}
 public function adjustment(Request $r){$this->only($r,'slu_head');$v=$r->validate(['user_id'=>'required|exists:users,id','amount'=>'required|integer|between:-1000,1000|not_in:0','reason'=>'required|string|min:10|max:3000']);$u=User::findOrFail($v['user_id']);abort_unless($u->account_type==='student'&&$u->organization_id,422);$q=ServiceRequest::create(['user_id'=>$u->id,'ticket'=>'ADJ-'.now()->format('Y').'-'.strtoupper(Str::random(8)),'kind'=>'achievement','status'=>'unit_approved','details'=>['title'=>'Point Adjustment','description'=>$v['reason'],'awarded_points'=>$v['amount'],'adjustment'=>true],'review_note'=>$v['reason'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);$this->audit($r,'request.unit_approved',$q->ticket,$v['reason']);return back()->with('success','Point adjustment sent to Assistant for forwarding. Points apply after final Admin approval.');}
 public function disposition(Request $r,ServiceRequest $serviceRequest){abort_unless($r->user()->role===$serviceRequest->office(),403);$v=$r->validate(['action'=>'required|in:archive,restore,release','review_note'=>'required|string|min:5|max:3000']);DB::transaction(function()use($r,$serviceRequest,$v){$q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);abort_unless(in_array($q->status,['approved','rejected','cancelled']),422);$details=$q->details;if($v['action']==='release'){abort_unless($q->status==='approved'&&in_array($q->kind,['lost','found','id-replacement']),422);$details['released']=true;$details['released_at']=now()->toIso8601String();}else $details['archived']=$v['action']==='archive';$q->update(['details'=>$details]);$this->audit($r,'request.'.$v['action'],$q->ticket,$v['review_note']);});return back()->with('success','Record updated.');}
}
