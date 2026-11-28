<?php
namespace App\Http\Controllers;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event,Point};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage,Validator};
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
  return view('staff.index',['requests'=>$query->latest()->paginate(20)->withQueryString(),'counts'=>$this->query($r)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status'),'users'=>User::orderBy('name')->get(),'assignments'=>in_array($r->user()->role,['admin','cvu_head'])?Assignment::with('user')->latest()->get():collect(),'attendance'=>Attendance::with(['user','assignment','event'])->where('status','pending')->get()->filter(fn($a)=>$r->user()->role==='admin'||($a->assignment_id&&$r->user()->role==='cvu_head')||($a->event_id&&$r->user()->role==='slu_head')),'journals'=>in_array($r->user()->role,['admin','cvu_head'])?Journal::with('assignment.user')->where('status','pending')->get():collect(),'events'=>Event::latest()->get(),'audit'=>in_array($r->user()->role,['admin','assistant'])?DB::table('audit_logs')->latest('id')->limit(30)->get():collect()]);
 }
 public function review(Request $r,ServiceRequest $serviceRequest){
  $this->requireCondition(in_array($r->user()->role,['admin','assistant'])||$serviceRequest->office()===$r->user()->role,403);
  $isMembershipRejection=$r->user()->role==='admin'&&$serviceRequest->kind==='leadership'&&$r->input('status')==='rejected';
  $unitApproval=in_array($r->input('status'),['unit_approved','approved'],true);
  $remarksOptional=$unitApproval&&match($serviceRequest->kind){
   'scholarship'=>$r->user()->role==='sfu_head',
   'leadership'=>$r->user()->role==='slu_head',
   'activity'=>$r->user()->role==='slu_head'&&empty($serviceRequest->details['completion_report']),
   'achievement'=>$r->user()->role==='slu_head'&&empty($serviceRequest->details['adjustment']),
   default=>false,
  };
  $v=$r->validate(
   ['status'=>'required|in:in_review,needs_revision,unit_approved,awaiting_admin,approved,rejected','review_note'=>($remarksOptional?'nullable':'required').'|string|min:5|max:3000','discount_rate'=>'nullable|integer|between:0,100','identity_verified'=>'nullable|boolean','hymn_completed'=>'nullable|boolean','awarded_points'=>'nullable|integer|between:-1000,1000'],
   [],
   $isMembershipRejection?['review_note'=>'reason for rejection']:[]
  );
  $note=$v['review_note']??null;
  // Preserve the unit-head API action while distinguishing it from final approval.
  if($v['status']==='approved'&&in_array($r->user()->role,['sfu_head','cvu_head','slu_head']))$v['status']='unit_approved';
  DB::transaction(function()use($r,$serviceRequest,$v,$note){
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
   $this->requireCondition(array_key_exists($v['status'],$q->reviewActions($r->user())),422,'This action is not allowed at the current review stage. Unit approval must be forwarded by the assistant before admin approval.');
   $details=$q->details;
   if($q->kind==='readmission'&&$v['status']==='unit_approved'){
    $interview=validator(['interview_at'=>$details['interview_at']??null],['interview_at'=>'required|date|before_or_equal:now']);
    $this->requireCondition($interview->passes(),422,'Schedule and complete the interview before approving re-admission.');
   }
   if($q->kind==='id-replacement'&&$v['status']==='unit_approved'){
    $requiresAffidavit=in_array($details['replacement_reason']??null,config('portal.id_replacement_affidavit_reasons'),true);
    $this->requireCondition(!$requiresAffidavit||$q->attachmentPath('evidence'),422,'An affidavit of loss is required before approving a lost or stolen ID replacement.');
    $payment=validator(['details'=>$details],config('portal.id_replacement_payment_rules'));
    $this->requireCondition($payment->passes(),422,'A receipt number and valid payment amount are required before approving ID replacement.');
    $details['student_name']??=$q->user->name;
   }
   if($q->kind==='clearance'&&in_array($v['status'],['unit_approved','approved']))$this->requireCondition(!Assignment::where('user_id',$q->user_id)->where('status','active')->exists(),422,'Complete the active community service record before clearing this student.');
   if($q->kind==='scholarship'&&in_array($v['status'],['unit_approved','approved'])){
    $rate=$v['discount_rate']??($details['discount_rate']??null);$this->requireCondition($rate&&$rate>0&&$rate<=100,422,'Specify a discount rate.');$details['discount_rate']=(int)$rate;
   }
   if($q->kind==='achievement'&&$v['status']==='unit_approved'&&isset($v['awarded_points']))$details['awarded_points']=(int)$v['awarded_points'];
   if($q->kind==='clearance'&&$v['status']==='unit_approved'){$this->requireCondition(($details['identity_verified']??false)||$r->boolean('identity_verified'),422,'Verify the student identity before clearing.');$this->requireCondition(($details['hymn_completed']??false)||$r->boolean('hymn_completed'),422,'Confirm the UCU Hymn requirement before clearing.');$details['identity_verified']=true;$details['hymn_completed']=true;}
   if($v['status']==='approved'){
    if($q->kind==='leadership')$q->user->update(['organization_id'=>$details['organization_id'],'position'=>$details['position']]);
    if($q->kind==='achievement')Point::firstOrCreate(['source'=>'achievement:'.$q->id],['user_id'=>$q->user_id,'amount'=>$details['awarded_points']??(json_decode(DB::table('settings')->where('key','staff.points-rules')->value('value')??'[]',true)['achievement']??100),'reason'=>$details['title']]);
    if($q->kind==='activity'){if($details['completion_report']??false)\App\Support\EventScoring::approveCompletion($q);else{$rules=json_decode(DB::table('settings')->where('key','staff.points-rules')->value('value')??'[]',true);$event=Event::create(['title'=>$details['title'],'description'=>$details['description'],'venue'=>$details['venue'],'starts_at'=>$details['starts_at'],'ends_at'=>$details['ends_at'],'capacity'=>$details['capacity'],'points'=>$rules['participant']??$details['points']]);\App\Support\EventScoring::register($event,$q);}}
    if($q->kind==='seminar'){
     $e=Event::lockForUpdate()->findOrFail($details['event_id']);$registered=$e->attendances()->where('user_id',$q->user_id)->where('status','<>','cancelled')->exists();
     $this->requireCondition($e->type==='seminar'&&in_array($e->status,['published','completed'])&&($registered||$e->starts_at->isFuture()),422,'Seminar registration is closed.');
     $this->rejectIf(!$registered&&$e->attendances()->where('status','<>','cancelled')->count()>=$e->capacity,422,'Seminar is full.');
     if(!$registered)Attendance::updateOrCreate(['user_id'=>$q->user_id,'event_id'=>$e->id],['status'=>'pending']);
    }
   }
   if($q->kind==='clearance'&&in_array($v['status'],['unit_approved','approved']))$details['appointment_status']=$v['status']==='approved'?'completed':'confirmed';
   if($q->kind==='readmission'&&$v['status']==='approved'){$details['issued_at']=now()->toIso8601String();$details['issued_by']=$r->user()->name;$details['sent_at']=now()->toIso8601String();}
   if($q->kind==='seminar'&&$v['status']==='rejected')Attendance::where('event_id',$details['event_id']??0)->where('user_id',$q->user_id)->where('status','pending')->update(['status'=>'cancelled']);
   $q->update(['status'=>$v['status'],'review_note'=>$note,'details'=>$details,'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);
   $this->audit($r,'request.'.$v['status'],$q->ticket,$note??'');
   if($v['status']==='unit_approved')\App\Support\StaffAlerts::role('assistant','Unit approved: '.$q->ticket,'/workspace/request/'.$q->id);
   if($v['status']==='awaiting_admin')\App\Support\StaffAlerts::role('admin','Forwarded transaction: '.$q->ticket,'/workspace/request/'.$q->id);
   if(in_array($v['status'],['approved','needs_revision','rejected']))\App\Support\StaffAlerts::role($q->office(),'Review update: '.$q->ticket,'/workspace/request/'.$q->id,'feedback',$r->user()->id);
   $studentUrl=rtrim(config('app.student_portal_url','http://127.0.0.1:8001'),'/').(in_array($q->kind,['readmission','clearance','seminar'])&&$q->status!=='needs_revision'?'/cvu/submitted/':'/requests/').$q->id;
   $title=$q->ticket.' '.$v['status'];
   $subject='iServe request update: '.$q->ticket;
   $message=$note??'';
   $statusText='Your request '.$q->ticket.' is now '.str_replace('_',' ',$v['status']).'.';
   if($q->kind==='id-replacement'){
    $title='ID replacement '.str_replace('_',' ',$v['status']).': '.$q->ticket;
    $subject='iServe '.$title;
    if($v['status']==='approved')$message=trim("Your request is approved. You will receive an update when the replacement ID is reprinted.\n\n".$message);
   }
   if($q->user->preferences['in_app']??true)$q->user->notifications()->create(['id'=>Str::uuid(),'type'=>'request','data'=>['title'=>$title,'url'=>$studentUrl,'message'=>$message]]);
   \App\Support\TransactionEmail::sendAfterCommit(
    $q->user,
    $subject,
    $statusText."\n\n".($message?"$message\n\n":'').'View your request: '.$studentUrl,
    ['request_id'=>$q->id],
   );
  });return back()->with('success','Review saved. Notifications follow the applicant’s preferences.');
 }
 public function assignment(Request $r){
  $this->office($r,'cvu_head');$v=$r->validate(['user_id'=>'required|exists:users,id','offense'=>'required|string|max:200','severity'=>'required|in:Minor,Major','department'=>'required|string|max:120','supervisor'=>'required|string|max:120','required_hours'=>'required|integer|between:1,500','note'=>'nullable|string|max:3000']);
  \App\Support\CommunityServiceHours::validate($v['severity'],(int)$v['required_hours']);
  DB::transaction(function()use($r,$v){
   $user=User::lockForUpdate()->findOrFail($v['user_id']);$this->requireCondition($user->account_type==='student',422);
   unset($v['required_hours']);$a=Assignment::create([...$v,'record_number'=>'CV-'.now()->format('Y').'-'.strtoupper(Str::random(6)),'required_minutes'=>$r->integer('required_hours')*60]);
   $this->audit($r,'assignment.create',$a->record_number);
  });return back()->with('success','Community service record created.');
 }
 public function attendance(Request $r,Attendance $attendance){
  $this->office($r,$attendance->assignment_id||$attendance->event?->type==='seminar'?'cvu_head':'slu_head');$r->validate(['status'=>'required|in:approved,rejected','review_note'=>'required|string|min:5|max:1000']);
  DB::transaction(function()use($r,$attendance){
   $a=Attendance::lockForUpdate()->findOrFail($attendance->id);$this->requireCondition($a->status==='pending'&&($a->time_out||$a->event?->type==='seminar'),422);
   if($a->event?->type==='seminar'){
    $this->requireCondition($a->event->status!=='cancelled'&&($r->status!=='approved'||$a->event->starts_at->lte(now())),422,'Seminar attendance can be verified only after the seminar starts.');
    if($r->status==='rejected'){$q=ServiceRequest::where('kind','seminar')->where('user_id',$a->user_id)->where('details->event_id',$a->event_id)->whereNotIn('status',['cancelled','rejected'])->latest('id')->lockForUpdate()->first();if($q){$q->update(['status'=>'needs_revision','review_note'=>$r->review_note,'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);$this->audit($r,'request.needs_revision',$q->ticket,$r->review_note);if($q->user->preferences['in_app']??true)$q->user->notifications()->create(['id'=>Str::uuid(),'type'=>'request','data'=>json_encode(['title'=>'Seminar attendance requires revision','url'=>rtrim(env('USER_PORTAL_URL','http://127.0.0.1:8001'),'/').'/requests/'.$q->id,'message'=>$r->review_note])]);}}
   }
   if($a->event_id&&$a->event->type!=='seminar')$this->requireCondition($a->reflection,422,'A reflection is required.');
   $a->update($r->only('status','review_note'));
   if($a->event_id&&$a->event->type!=='seminar'&&$r->status==='approved')Point::firstOrCreate(['source'=>'attendance:'.$a->id],['user_id'=>$a->user_id,'amount'=>$a->event->points,'reason'=>$a->event->title]);
   $this->audit($r,'attendance.'.$r->status,(string)$a->id,$r->review_note);
   if($a->assignment_id||$a->event?->type==='leadership'){
    $path=$a->assignment_id?'/portal/values/community-service?assignment='.$a->assignment_id:'/portal/leadership/events#event-'.$a->event_id;
    \App\Support\StaffAlerts::account($a->user,'Attendance '.$a->status,$path,message:$r->review_note);
   }
  });
  $response=back()->with('success','Attendance reviewed.');
  $dialog='event-'.$attendance->event_id.'-attendance';
  if($attendance->event_id&&$r->input('_dialog')===$dialog)$response->with('attendance_dialog',$dialog);
  return $response;
 }
 public function journal(Request $r,Journal $journal){
  $this->office($r,'cvu_head');$r->validate(['status'=>'required|in:approved,rejected']);
  $this->requireCondition($journal->status==='pending',422);$journal->update(['status'=>$r->status]);$this->audit($r,'journal.'.$r->status,(string)$journal->id);\App\Support\StaffAlerts::account($journal->assignment->user,'Duty journal '.$journal->status,'/portal/values/journal?assignment='.$journal->assignment_id);return back()->with('success','Journal reviewed.');
 }
 public function complete(Request $r,Assignment $assignment){
  $this->office($r,'cvu_head');
  DB::transaction(function()use($r,$assignment){
   $a=Assignment::lockForUpdate()->findOrFail($assignment->id);$this->requireCondition($a->status==='active',422);
   $this->rejectIf($a->attendances()->where('status','in_progress')->whereNull('time_out')->exists(),422,'Record Time Out before completing this assignment.');
   $this->requireCondition($a->approvedMinutes()>=$a->required_minutes&&$a->journals()->where('status','approved')->whereIn('module',array_keys(config('portal.journals')))->distinct()->count('module')===count(config('portal.journals')),422,'Verified hours and all four approved journal modules are required.');
   $a->update(['status'=>'completed']);$this->audit($r,'assignment.complete',$a->record_number);
  });
  $hasActiveAssignments=Assignment::where('user_id',$assignment->user_id)->where('status','active')->exists();
  return back()->with('success',$hasActiveAssignments?'Record completed. The student still has active service assignments.':'Record completed. The student may request clearance.');
 }
 public function event(Request $r){
  $this->office($r,'slu_head');
  $isPast=$r->input('event_period')==='past';
  $dateTimeFormats='Y-m-d\TH:i,Y-m-d H:i,Y-m-d\TH:i:s,Y-m-d H:i:s';
  $validator=Validator::make($r->all(),[
   'title'=>'bail|required|string|max:150',
   'description'=>'bail|required|string|max:3000',
   'venue'=>'bail|required|string|max:120',
   'starts_at'=>'bail|required|date_format:'.$dateTimeFormats.($isPast?'|before:now':'|after:now'),
   'ends_at'=>'bail|required|date_format:'.$dateTimeFormats.($isPast?'|before_or_equal:now':''),
   'points'=>'bail|required|integer|between:0,1000',
   'capacity'=>'bail|required|integer|between:1,5000',
   'type'=>'required|in:leadership,seminar',
   'custom_venue'=>'sometimes|boolean',
   'event_period'=>'sometimes|required|in:past,future',
  ],[
   'title.required'=>'Enter an event title.',
   'description.required'=>'Enter an event description.',
   'venue.required'=>'Select a venue or enter a venue name.',
   'starts_at.required'=>'Choose a start date and time.',
   'starts_at.date_format'=>'Enter a valid start date and time.',
   'starts_at.after'=>'The start date and time must be in the future.',
   'starts_at.before'=>'The start date and time must be in the past.',
   'ends_at.required'=>'Choose an end date and time.',
   'ends_at.date_format'=>'Enter a valid end date and time.',
   'ends_at.before_or_equal'=>'The end date and time cannot be in the future.',
   'capacity.required'=>'Enter the event capacity.',
   'capacity.integer'=>'Capacity must be a whole number.',
   'capacity.between'=>'Capacity must be between 1 and 5,000.',
   'points.required'=>'Enter the participation points, or enter 0.',
   'points.integer'=>'Participation points must be a whole number.',
   'points.between'=>'Participation points must be between 0 and 1,000.',
  ],['points'=>'participation points']);
  $validator->after(function($validator)use($r){
   if($validator->errors()->hasAny(['starts_at','ends_at']))return;
   if(\Carbon\Carbon::parse($r->input('ends_at'))->lte(\Carbon\Carbon::parse($r->input('starts_at')))){
    $validator->errors()->add('ends_at','The end date and time must be after the start date and time.');
   }
  });
  $v=$validator->validateWithBag($isPast?'createPastEvent':'createEvent');
  unset($v['custom_venue'],$v['event_period']);
  $v['status']=$isPast?'completed':'published';
  DB::transaction(function()use($r,$v){
   $e=Event::create($v);
   if($e->type==='leadership'){
    if($r->boolean('custom_venue'))Event::setVenueAvailability($e->venue,true);
    else Event::rememberVenues($e->venue);
   }
   $this->audit($r,'event.create',(string)$e->id);
  });
  return back()->with('success',$isPast?'Past event saved to the archive.':'Event published.');
 }
 public function cancelEvent(Request $r,Event $event){$this->office($r,'slu_head');$event->update(['status'=>'cancelled']);$this->audit($r,'event.cancel',(string)$event->id);return back()->with('success','Event cancelled.');}
 public function user(Request $r){
  $this->office($r,'admin');$v=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users','username'=>'required|alpha_dash|max:50|unique:users','role'=>'required|in:admin,assistant,sfu_head,cvu_head,slu_head','password'=>'required|min:12']);
  User::create([...$v,'account_type'=>'employee','profile_complete'=>true]);$this->audit($r,'staff.create',$v['email']);return back()->with('success','Staff account created.');
 }
 public function evidence(Request $r,Attendance $attendance,string $side){
  $this->office($r,$attendance->assignment_id||$attendance->event?->type==='seminar'?'cvu_head':'slu_head');
  $this->requireCondition(in_array($side,['in','out'],true),404);
  $path=$attendance->{'photo_'.$side};
  $this->requireCondition($path&&Storage::exists($path),404);
  if(!$r->boolean('preview'))return Storage::download($path);

  $mime=Storage::mimeType($path);
  $this->requireCondition(in_array($mime,['image/jpeg','image/png','image/webp','image/gif','image/bmp','image/x-ms-bmp'],true),404);
  return Storage::response($path,null,[
   'Content-Type'=>$mime,
   'Cache-Control'=>'private, no-store',
   'X-Content-Type-Options'=>'nosniff',
  ]);
 }
 public function export(Request $r){
  $rows=$this->query($r)->latest()->get();$this->audit($r,'requests.export','CSV');
  return response()->streamDownload(function()use($rows){$f=fopen('php://output','w');fputcsv($f,['Ticket','Applicant','Type','Status','Submitted','Reviewer note']);foreach($rows as $q){$row=[$q->ticket,$q->user->name,$q->kind,$q->status,$q->created_at->toDateTimeString(),$q->review_note];fputcsv($f,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/',(string)$v)?"'".$v:$v,$row));}fclose($f);},'iserve-requests-'.now()->format('Ymd').'.csv',['Content-Type'=>'text/csv']);
 }
}


