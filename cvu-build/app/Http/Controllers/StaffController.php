<?php
namespace App\Http\Controllers;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event,Point};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
class StaffController extends Controller {
 private function query(Request $r){$q=ServiceRequest::with(['user','reviewer']);if(!in_array($r->user()->role,['admin','assistant']))$q->whereIn('kind',config('portal.office.'.$r->user()->role,[]));return $q;}
 private function office(Request $r,string $role){$this->requireCondition(in_array($r->user()->role,['admin',$role]),403);}
 private function audit(Request $r,string $action,string $subject,string $details=''){DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>$action,'subject'=>$subject,'details'=>$details,'created_at'=>now()]);}
 public function index(Request $r){
  $query=$this->query($r);
  if($r->filled('status'))$query->where('status',$r->status);
  if($r->filled('kind'))$query->where('kind',$r->kind);
  if($r->filled('search'))$query->where(fn($q)=>$q->where('ticket','like','%'.$r->search.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$r->search.'%')));
  return view('staff.index',['requests'=>$query->latest()->paginate(20)->withQueryString(),'counts'=>$this->query($r)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status'),'users'=>User::orderBy('name')->get(),'assignments'=>in_array($r->user()->role,['admin','cvu_head'])?Assignment::with('user')->latest()->get():collect(),'attendance'=>Attendance::with(['user','assignment','event'])->where('status','pending')->get()->filter(fn($a)=>$r->user()->role==='admin'||($a->assignment_id&&$r->user()->role==='cvu_head')||($a->event_id&&$r->user()->role===($a->event?->type==='seminar'?'cvu_head':'slu_head'))),'journals'=>in_array($r->user()->role,['admin','cvu_head'])?Journal::with('assignment.user')->where('status','pending')->get():collect(),'events'=>Event::latest()->get(),'audit'=>in_array($r->user()->role,['admin','assistant'])?DB::table('audit_logs')->latest('id')->limit(30)->get():collect()]);
 }
 public function review(Request $r,ServiceRequest $serviceRequest){
  $this->requireCondition(in_array($r->user()->role,['admin','assistant'])||$serviceRequest->office()===$r->user()->role,403);
  $v=$r->validate(['status'=>'required|in:in_review,needs_revision,unit_approved,awaiting_admin,approved,rejected','review_note'=>'required|string|min:5|max:3000','discount_rate'=>'nullable|integer|between:0,100','identity_verified'=>'nullable|boolean','hymn_completed'=>'nullable|boolean']);
  // Preserve the unit-head API action while distinguishing it from final approval.
  if($v['status']==='approved'&&in_array($r->user()->role,['sfu_head','cvu_head','slu_head']))$v['status']='unit_approved';
  DB::transaction(function()use($r,$serviceRequest,$v){
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
   $this->requireCondition(array_key_exists($v['status'],$q->reviewActions($r->user())),422,'This action is not allowed at the current review stage. Unit approval must be forwarded by the assistant before admin approval.');
   $details=$q->details;
   if($q->kind==='scholarship'&&in_array($v['status'],['unit_approved','approved'])){
    $rate=$v['discount_rate']??($details['discount_rate']??null);$this->requireCondition($rate&&$rate>0&&$rate<=100,422,'Specify a discount rate.');$details['discount_rate']=(int)$rate;
   }
   if($q->kind==='clearance'&&$v['status']==='unit_approved'){$this->requireCondition(($details['identity_verified']??false)||$r->boolean('identity_verified'),422,'Verify the student identity before clearing.');$this->requireCondition(($details['hymn_completed']??false)||$r->boolean('hymn_completed'),422,'Confirm the UCU Hymn requirement before clearing.');$details['identity_verified']=true;$details['hymn_completed']=true;}
   if($v['status']==='approved'){
    if($q->kind==='leadership')$q->user->update(['organization_id'=>$details['organization_id'],'position'=>$details['position']]);
    if($q->kind==='achievement')Point::firstOrCreate(['source'=>'achievement:'.$q->id],['user_id'=>$q->user_id,'amount'=>$details['awarded_points']??(json_decode(DB::table('settings')->where('key','staff.points-rules')->value('value')??'[]',true)['achievement']??100),'reason'=>$details['title']]);
    if($q->kind==='activity')Event::create(['title'=>$details['title'],'description'=>$details['description'],'venue'=>$details['venue'],'starts_at'=>$details['starts_at'],'ends_at'=>$details['ends_at'],'capacity'=>$details['capacity'],'points'=>$details['points']]);
    if($q->kind==='seminar'){
     $e=Event::lockForUpdate()->findOrFail($details['event_id']);$this->requireCondition($e->status==='published'&&$e->starts_at->isFuture(),422,'Seminar registration is closed.');
     $this->rejectIf($e->attendances()->where('status','<>','cancelled')->where('user_id','<>',$q->user_id)->count()>=$e->capacity,422,'Seminar is full.');
     Attendance::firstOrCreate(['user_id'=>$q->user_id,'event_id'=>$e->id]);
    }
   }
   $q->update(['status'=>$v['status'],'review_note'=>$v['review_note'],'details'=>$details,'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);
   $this->audit($r,'request.'.$v['status'],$q->ticket,$v['review_note']);
   if($v['status']==='unit_approved')\App\Support\StaffAlerts::role('assistant','Unit approved: '.$q->ticket,'/workspace/request/'.$q->id);
   if($v['status']==='awaiting_admin')\App\Support\StaffAlerts::role('admin','Forwarded transaction: '.$q->ticket,'/workspace/request/'.$q->id);
   if(in_array($v['status'],['approved','needs_revision','rejected']))\App\Support\StaffAlerts::role($q->office(),'Review update: '.$q->ticket,'/workspace/request/'.$q->id,'feedback');
   if($q->user->preferences['in_app']??true){
    $scholarPortalOpened=$q->kind==='scholarship'&&$v['status']==='unit_approved';
    $q->user->notifications()->create(['id'=>Str::uuid(),'type'=>'request','data'=>json_encode([
     'title'=>$scholarPortalOpened?'Your Scholar Portal is now available':$q->ticket.' '.$v['status'],
     'url'=>$scholarPortalOpened?'/portal/scholar':'/requests/'.$q->id,
     'message'=>$scholarPortalOpened?'The SFU Head approved your application at unit level. Open Scholarship and Financial Unit from Choose Unit to access your portal. '.$v['review_note']:$v['review_note'],
    ])]);
   }
   if($q->user->preferences['email']??true)DB::afterCommit(function()use($q,$v){
    try{\Illuminate\Support\Facades\Mail::raw('Your request '.$q->ticket.' is now '.str_replace('_',' ',$v['status']).".\n\n".$v['review_note']."\n\nView your request: ".url('/requests/'.$q->id),fn($mail)=>$mail->to($q->user->email)->subject('iServe request update: '.$q->ticket));}
    catch(\Throwable $error){\Illuminate\Support\Facades\Log::warning('Request email delivery failed',['request_id'=>$q->id]);}
   });
  });return back()->with('success','Review saved. Notifications follow the applicant’s preferences.');
 }
 public function assignment(Request $r){
  $this->office($r,'cvu_head');$v=$r->validate(['user_id'=>'required|exists:users,id','offense'=>'required|string|max:200','severity'=>'required|in:Minor,Major','department'=>'required|string|max:120','supervisor'=>'required|string|max:120','required_hours'=>'required|integer|between:1,500','note'=>'nullable|string|max:3000']);
  DB::transaction(function()use($r,$v){
   $user=User::lockForUpdate()->findOrFail($v['user_id']);$this->requireCondition($user->account_type==='student',422);$this->rejectIf($user->assignment&&$user->assignment->status==='active',422,'Student already has an active record.');
   unset($v['required_hours']);$a=Assignment::create([...$v,'record_number'=>'CV-'.now()->format('Y').'-'.strtoupper(Str::random(6)),'required_minutes'=>$r->integer('required_hours')*60]);
   $this->audit($r,'assignment.create',$a->record_number);
  });return back()->with('success','Community service record created.');
 }
 public function attendance(Request $r,Attendance $attendance){
  $this->office($r,($attendance->assignment_id||$attendance->event?->type==='seminar')?'cvu_head':'slu_head');$r->validate(['status'=>'required|in:approved,rejected','review_note'=>'required|string|min:5|max:1000']);
  DB::transaction(function()use($r,$attendance){
   $a=Attendance::lockForUpdate()->findOrFail($attendance->id);$this->requireCondition($a->status==='pending'&&($a->time_out||$a->event?->type==='seminar'),422);
   if($a->event_id)$this->requireCondition($a->reflection,422,'A reflection is required.');
   $a->update($r->only('status','review_note'));
   if($a->event_id&&$r->status==='approved')Point::firstOrCreate(['source'=>'attendance:'.$a->id],['user_id'=>$a->user_id,'amount'=>$a->event->points,'reason'=>$a->event->title]);
   $this->audit($r,'attendance.'.$r->status,(string)$a->id,$r->review_note);
  });return back()->with('success','Attendance reviewed.');
 }
 public function journal(Request $r,Journal $journal){
  $this->office($r,'cvu_head');$r->validate(['status'=>'required|in:approved,rejected']);
  $this->requireCondition($journal->status==='pending',422);$journal->update(['status'=>$r->status]);$this->audit($r,'journal.'.$r->status,(string)$journal->id);return back()->with('success','Journal reviewed.');
 }
 public function complete(Request $r,Assignment $assignment){
  $this->office($r,'cvu_head');
  DB::transaction(function()use($r,$assignment){
   $a=Assignment::lockForUpdate()->findOrFail($assignment->id);$this->requireCondition($a->status==='active',422);
   $this->requireCondition($a->approvedMinutes()>=$a->required_minutes&&$a->journals()->where('status','approved')->whereIn('module',array_keys(config('portal.journals')))->distinct()->count('module')===count(config('portal.journals')),422,'Verified hours and all four approved journal modules are required.');
   $a->update(['status'=>'completed']);$this->audit($r,'assignment.complete',$a->record_number);
  });return back()->with('success','Record completed. The student may request clearance.');
 }
 public function event(Request $r){
  $this->office($r,'slu_head');$v=$r->validate(['title'=>'required|string|max:150','description'=>'required|string|max:3000','venue'=>'required|string|max:120','starts_at'=>'required|date|after:now','ends_at'=>'required|date|after:starts_at','points'=>'required|integer|between:0,1000','capacity'=>'required|integer|between:1,5000','type'=>'required|in:leadership,seminar']);
  $e=Event::create($v);$this->audit($r,'event.create',(string)$e->id);return back()->with('success','Event published.');
 }
 public function cancelEvent(Request $r,Event $event){$this->office($r,'slu_head');$event->update(['status'=>'cancelled']);$this->audit($r,'event.cancel',(string)$event->id);return back()->with('success','Event cancelled.');}
 public function user(Request $r){
  $this->office($r,'admin');$v=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users','username'=>'required|alpha_dash|max:50|unique:users','role'=>'required|in:admin,assistant,sfu_head,cvu_head,slu_head','password'=>'required|min:12']);
  User::create([...$v,'account_type'=>'employee','profile_complete'=>true]);$this->audit($r,'staff.create',$v['email']);return back()->with('success','Staff account created.');
 }
 public function evidence(Request $r,Attendance $attendance,string $side){
  $this->office($r,($attendance->assignment_id||$attendance->event?->type==='seminar')?'cvu_head':'slu_head');$this->requireCondition(in_array($side,['in','out']),404);$p=$attendance->{'photo_'.$side};$this->requireCondition($p&&Storage::exists($p),404);return Storage::download($p);
 }
 public function export(Request $r){
  $rows=$this->query($r)->latest()->get();$this->audit($r,'requests.export','CSV');
  return response()->streamDownload(function()use($rows){$f=fopen('php://output','w');fputcsv($f,['Ticket','Applicant','Type','Status','Submitted','Reviewer note']);foreach($rows as $q){$row=[$q->ticket,$q->user->name,$q->kind,$q->status,$q->created_at->toDateTimeString(),$q->review_note];fputcsv($f,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/',(string)$v)?"'".$v:$v,$row));}fclose($f);},'iserve-requests-'.now()->format('Ymd').'.csv',['Content-Type'=>'text/csv']);
 }
}


