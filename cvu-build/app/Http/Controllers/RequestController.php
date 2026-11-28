<?php
namespace App\Http\Controllers;
use App\Models\{ServiceRequest,Organization,Event};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
class RequestController extends Controller {
 public function form(Request $r,string $kind){
  if(in_array($kind,['readmission','clearance','seminar']))return app(CvuFlowController::class)->form($r,$kind);
  if($kind==='scholarship')return app(ScholarOnboardingController::class)->documents($r);
  $this->requireCondition(array_key_exists($kind,ServiceRequest::kinds()),404);
  if($kind==='activity'&&$r->user()->account_type==='student'&&!$r->user()->isStaff()&&!$r->user()->organization_id){
   return redirect('/apply/leadership');
  }
  if(in_array($kind,['activity','achievement']))$this->requireCondition($r->user()->organization_id,403,'Your SLU membership must be accepted first.');
  if(!$r->user()->profile_complete)return redirect('/profile/setup');
  if(in_array($kind,['scholarship','leadership','activity','achievement','seminar','clearance']))$this->requireCondition($r->user()->account_type==='student',403);
  if($kind==='clearance'){$configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);if($configured)config(['portal.fields.clearance.schedule'=>'select:'.implode(',',array_unique(array_column(array_filter($configured,fn($s)=>($s['open']??false)&&$s['date']>today()->format('Y-m-d')),'start')))]);}
  $draft=$kind==='activity'?DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first():null;
  return view('transactions.form',['kind'=>$kind,'draft'=>$draft,'draftDetails'=>$draft?json_decode($draft->details,true):[],'draftFiles'=>$draft?json_decode($draft->files,true):[],'organizations'=>Organization::visibleInStudentPortal()->get(),'seminars'=>Event::where('type','seminar')->where('status','published')->where('starts_at','>',now())->get()]);
 }
 public function store(Request $r,string $kind){
  if(in_array($kind,['readmission','clearance','seminar']))return app(CvuFlowController::class)->submit($r,$kind);
  if($kind==='scholarship')return app(ScholarOnboardingController::class)->store($r);
  $this->requireCondition(array_key_exists($kind,ServiceRequest::kinds()),404);
  if(in_array($kind,['activity','achievement']))$this->requireCondition($r->user()->organization_id,403,'Your SLU membership must be accepted first.');$this->requireCondition($r->user()->profile_complete,422,'Complete your profile first.');
  if(in_array($kind,['scholarship','leadership','activity','achievement','seminar','clearance']))$this->requireCondition($r->user()->account_type==='student',403);
  if($kind==='clearance'){$configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);if($configured)config(['portal.fields.clearance.schedule'=>'select:'.implode(',',array_unique(array_column($configured,'start')))]);}
  $rules=['declaration'=>'accepted'];$fields=config('portal.fields.'.$kind,[]);
  foreach($fields as $name=>$type){
   $rule='required|string|max:200';if($type==='textarea')$rule='required|string|min:10|max:5000';
   if($type==='date')$rule='required|date';
   if($type==='datetime-local')$rule='required|date|after_or_equal:today';
   if($type==='number')$rule='required|integer|min:1|max:'.($name==='points'?'1000':'5000');
   if(str_starts_with($type,'select:'))$rule='required|in:'.substr($type,7);
   if($type==='organization')$rule=['required','integer',Organization::visibleInStudentPortalRule()];
   if($type==='seminar')$rule='required|integer|exists:events,id';
   $rules['details.'.$name]=$rule;
  }
  foreach(config('portal.optional_fields.'.$kind,[]) as $name=>$type)$rules['details.'.$name]='nullable|string|max:500';
  if($kind==='complaint'){
   $rules['details.other_complaint']='exclude_unless:details.complaint_type,Other|required|string|max:500';
   if($r->user()->account_type==='student'){
    $rules['details.parent_guardian_name']='nullable|string|max:120';
    $rules['details.parent_guardian_relationship']='nullable|string|max:60';
    $rules['details.parent_guardian_phone']='nullable|string|max:40';
   }
  }
  if($kind==='clearance')$rules['details.appointment_date']='required|date|after:today';
  if($kind==='activity'){
   $rules['details.ends_at']='required|date|after:details.starts_at';
   $rules['details.capacity']='required|integer|min:1|max:'.config('portal.activity_capacity_max');
   $rules['details.off_campus']='sometimes|boolean';
  }
  foreach(config('portal.documents.'.$kind,[]) as $key=>$label)$rules['documents.'.$key]=(in_array($kind.'.'.$key,config('portal.optional_documents'))?'nullable':'required').'|file|mimes:pdf,jpg,jpeg,png|max:'.($kind==='activity'?'10240':'5120');
  if($kind==='id-replacement'){
   $rules+=config('portal.id_replacement_payment_rules');
   $rules['documents.evidence']='required_if:details.replacement_reason,'.implode(',',config('portal.id_replacement_affidavit_reasons')).'|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
  }
  $v=$r->validate($rules,[
   'details.other_complaint.required'=>'Enter your complaint type when you select Others.',
   'documents.evidence.required_if'=>'Upload an affidavit of loss for a lost or stolen ID.',
  ],['details.other_complaint'=>'custom complaint type','details.receipt_number'=>'receipt number','details.payment_amount'=>'payment amount']);$draft=$kind==='activity'?DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first():null;$paths=$draft?json_decode($draft->files,true):[];$newPaths=[];
  try{
   foreach($r->file('documents',[]) as $key=>$f)if(isset($rules['documents.'.$key])){
    $path=$f->store('requests');
    if(!$path)throw \Illuminate\Validation\ValidationException::withMessages(['documents.'.$key=>'The file could not be saved. Please upload it again.']);
    $paths[$key]=['path'=>$path,'name'=>$f->getClientOriginalName()];$newPaths[]=$path;
   }
   $q=DB::transaction(function()use($r,$kind,$v,$paths){
    // Lock the owner to serialize duplicate submissions.
    DB::table('users')->where('id',$r->user()->id)->update(['updated_at'=>now()]);
    $this->rejectIf(in_array($kind,['scholarship','leadership','clearance'])&&ServiceRequest::where('user_id',$r->user()->id)->where('kind',$kind)->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin'])->exists(),422,'You already have an open request of this type.');
    if($kind==='clearance'){
     $this->rejectIf($r->user()->assignments()->where('status','active')->exists(),422,'Finish your community service and final verification before requesting clearance.');
     $slot=ServiceRequest::where('kind','clearance')->whereIn('status',['pending','in_review','unit_approved','awaiting_admin','approved'])->get()->filter(fn($q)=>($q->details['appointment_date']??null)===$v['details']['appointment_date']&&($q->details['schedule']??null)===$v['details']['schedule'])->count();
     $configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->value('value')??'[]',true);$capacity=10;if($configured){$chosen=$configured[$v['details']['appointment_date'].' '.$v['details']['schedule']]??null;$this->requireCondition($chosen&&($chosen['open']??false),422,'This office appointment is not available. Choose an open date and time.');$capacity=(int)$chosen['capacity'];}
     $this->rejectIf($slot>=$capacity,422,'This appointment slot is full. Choose another time.');
    }
    if($kind==='seminar'){
     $event=Event::lockForUpdate()->findOrFail($v['details']['event_id']);$this->requireCondition($event->type==='seminar'&&$event->status==='published'&&$event->starts_at->isFuture(),422);
    }
    $details=$v['details'];
    if(in_array($kind,['activity','achievement'],true))$details['organization_id']=$r->user()->organization_id;
    if($kind==='id-replacement')$details['student_name']=$r->user()->name;
    $request=ServiceRequest::create(['user_id'=>$r->user()->id,'ticket'=>strtoupper(substr($kind,0,3)).'-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),'kind'=>$kind,'details'=>$details,'files'=>$paths]);
    \App\Support\TransactionEmail::receipt($request);
    return $request;
   });
  }catch(\Throwable $e){foreach($newPaths as $p)Storage::delete($p);throw $e;}
  if($draft){DB::table('proposal_drafts')->where('id',$draft->id)->delete();foreach(json_decode($draft->files,true) as $key=>$p)if(($paths[$key]['path']??null)!==$p['path'])Storage::delete($p['path']);}
  $alertTitle=$kind==='id-replacement'?'New ID replacement request: ':'New transaction: ';
  \App\Support\StaffAlerts::role($q->office(),$alertTitle.$q->ticket,'/workspace/request/'.$q->id,$kind==='activity'?'proposals':'submissions');
  return redirect('/requests/'.$q->id)->with('success','Your request has been submitted successfully.');
 }
 public function draft(Request $r){
  $this->requireCondition($r->user()->account_type==='student'&&$r->user()->profile_complete&&$r->user()->organization_id,403);
  $rules=[];foreach(config('portal.fields.activity') as $key=>$type)$rules['details.'.$key]='nullable|string|max:5000';
  $rules['details.capacity']='nullable|integer|min:1|max:'.config('portal.activity_capacity_max');
  $rules['details.off_campus']='sometimes|boolean';
  foreach(config('portal.documents.activity') as $key=>$label)$rules['documents.'.$key]='nullable|file|mimes:pdf,jpg,jpeg,png|max:10240';
  $v=$r->validate($rules);$draft=DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first();$files=$draft?json_decode($draft->files,true):[];$new=[];$replaced=[];
  try{foreach($r->file('documents',[]) as $key=>$f)if(isset($rules['documents.'.$key])){if(isset($files[$key]))$replaced[]=$files[$key]['path'];$path=$f->store('requests');$new[]=$path;$files[$key]=['path'=>$path,'name'=>$f->getClientOriginalName()];}
   DB::table('proposal_drafts')->updateOrInsert(['user_id'=>$r->user()->id],['details'=>json_encode($v['details']??[]),'files'=>json_encode($files),'created_at'=>$draft?->created_at??now(),'updated_at'=>now()]);
  }catch(\Throwable $e){foreach($new as $p)Storage::delete($p);throw $e;}
  foreach($replaced as $p)Storage::delete($p);
  return redirect('/apply/activity')->with('success','Draft saved. Your proposal has not been submitted for review.');
 }
 public function show(Request $r,ServiceRequest $serviceRequest){$this->access($r,$serviceRequest);if($r->user()->isStaff()&&$serviceRequest->office()==='cvu_head')return redirect()->away(rtrim(env('STAFF_PORTAL_URL','https://goldenrod-turkey-247730.hostingersite.com'),'/').'/workspace/request/'.$serviceRequest->id);$history=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('audit_logs.subject',$serviceRequest->ticket)->where('audit_logs.action','like','request.%')->orderBy('audit_logs.id')->select('audit_logs.*','users.name as actor_name','users.role as actor_role')->get();return view('transactions.show',['q'=>$serviceRequest,'history'=>$history]);}
 public function cancel(Request $r,ServiceRequest $serviceRequest){
  $this->requireCondition($serviceRequest->user_id===$r->user()->id,403);
  DB::transaction(function()use($r,$serviceRequest){
   \App\Models\User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $q=ServiceRequest::whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
   $this->requireCondition(in_array($q->status,['pending','needs_revision']),422,'This request can no longer be cancelled.');
   if($q->kind==='seminar'){
    $event=Event::whereKey($q->details['event_id']??0)->lockForUpdate()->first();
    $attendance=$event?->attendances()->where('user_id',$r->user()->id)->lockForUpdate()->first();
    $this->requireCondition(!$attendance||in_array($attendance->status,['pending','cancelled']),422,'Verified or ongoing attendance cannot be cancelled.');
    $attendance?->update(['status'=>'cancelled']);
   }
   $q->update(['status'=>'cancelled']);DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'request.cancelled','subject'=>$q->ticket,'created_at'=>now()]);
  });return back()->with('success','Request cancelled.');
 }
 public function revise(Request $r,ServiceRequest $serviceRequest){
  $this->requireCondition($serviceRequest->user_id===$r->user()->id&&$serviceRequest->status==='needs_revision',403);
  $isCompletionReport=!empty($serviceRequest->details['completion_report']);
  $types=$isCompletionReport?'pdf,docx,png,jpg,jpeg':'pdf,png,jpg,jpeg';
  $rules=['revision'=>'required|string|min:10|max:5000','documents.*'=>'nullable|file|mimes:'.$types.'|max:'.($serviceRequest->kind==='activity'?'10240':'5120')];
  if($serviceRequest->kind==='id-replacement'){
   foreach(config('portal.id_replacement_payment_rules') as $field=>$rule){
    $rules[$field]=filled(data_get($serviceRequest,$field))?'sometimes|'.$rule:$rule;
   }
   $evidence=$serviceRequest->files['evidence']??null;
   if(in_array($serviceRequest->details['replacement_reason']??null,config('portal.id_replacement_affidavit_reasons'),true)
    &&blank(is_array($evidence)?($evidence['path']??null):$evidence))$rules['documents.evidence']='required|file|mimes:pdf,jpg,jpeg,png|max:5120';
  }
  $v=$r->validate($rules,['documents.evidence.required'=>'Upload an affidavit of loss for a lost or stolen ID.'],['details.receipt_number'=>'receipt number','details.payment_amount'=>'payment amount']);
  $files=$serviceRequest->files??[];$stored=[];$replaced=[];
  $allowed=$isCompletionReport?['completion_report']:array_unique([...array_keys(config('portal.documents.'.$serviceRequest->kind,[])),...array_keys($files)]);
  foreach(array_keys($r->file('documents',[])) as $key)$this->requireCondition(in_array($key,$allowed,true),422,'Unknown document requirement.');
  try{
  foreach($r->file('documents',[]) as $key=>$f){
   if(isset($files[$key]['path']))$replaced[]=$files[$key]['path'];
   $path=$f->store('requests');
   if(!$path)throw \Illuminate\Validation\ValidationException::withMessages(['documents.'.$key=>'The file could not be saved. Please upload it again.']);
   $stored[]=$path;$files[$key]=['path'=>$path,'name'=>$f->getClientOriginalName()];
  }
  DB::transaction(function()use($serviceRequest,$r,$files,$v){
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);$this->requireCondition($q->status==='needs_revision',422);
   $details=[...$q->details,'revision'=>$r->revision];if($q->kind==='clearance'){unset($details['identity_verified'],$details['hymn_completed']);}
   if($q->kind==='id-replacement'){
    $details=array_replace($details,$v['details']??[]);
    $details['student_name']??=$q->user->name;
   }
   if($q->kind==='seminar'){$event=Event::whereKey($details['event_id']??0)->lockForUpdate()->first();$this->requireCondition($event&&in_array($event->status,['published','completed']),422,'This seminar is no longer available for revision.');$attendance=$event->attendances()->where('user_id',$q->user_id)->lockForUpdate()->first();$this->requireCondition($attendance&&$attendance->status!=='cancelled',422,'This registration is no longer active.');if($attendance->status==='rejected')$attendance->update(['status'=>'pending','reflection'=>$r->revision]);}
   $q->update(['details'=>$details,'files'=>$files,'status'=>'pending']);DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'request.resubmitted','subject'=>$q->ticket,'created_at'=>now()]);\App\Support\StaffAlerts::role($q->office(),'Revised request: '.$q->ticket,($q->kind==='leadership'?'/workspace/organizations?review='.$q->id:'/workspace/request/'.$q->id),'resubmitted');
   \App\Support\TransactionEmail::receipt($q, resubmitted:true);
  });
  }catch(\Throwable $e){foreach($stored as $path)Storage::delete($path);throw $e;}
  foreach($replaced as $path)Storage::delete($path);
  return back()->with('success','Revision sent for review.');
 }
 public function download(Request $r,ServiceRequest $serviceRequest,string $key){$this->access($r,$serviceRequest);$file=$serviceRequest->files[$key]??null;$this->requireCondition($file&&Storage::exists($file['path']),404);return Storage::download($file['path'],basename($file['name']));}
 private function access(Request $r,ServiceRequest $q){$this->requireCondition($q->user_id===$r->user()->id||($r->user()->isStaff()&&(in_array($r->user()->role,['admin','assistant'])||$q->office()===$r->user()->role)),403);}
}

