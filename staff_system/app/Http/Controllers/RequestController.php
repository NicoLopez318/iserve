<?php
namespace App\Http\Controllers;
use App\Models\{ServiceRequest,Organization,Event};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
class RequestController extends Controller {
 public function form(Request $r,string $kind){
  $this->requireCondition(array_key_exists($kind,ServiceRequest::kinds()),404);
  if(!$r->user()->profile_complete)return redirect('/profile/setup');
  if(in_array($kind,['scholarship','leadership','activity','achievement','seminar','clearance']))$this->requireCondition($r->user()->account_type==='student',403);
  if($kind==='clearance'){$configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);if($configured)config(['portal.fields.clearance.schedule'=>'select:'.implode(',',array_unique(array_column(array_filter($configured,fn($s)=>($s['open']??false)&&$s['date']>today()->format('Y-m-d')),'start')))]);}
  $draft=$kind==='activity'?DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first():null;
  return view('transactions.form',['kind'=>$kind,'draft'=>$draft,'draftDetails'=>$draft?json_decode($draft->details,true):[],'draftFiles'=>$draft?json_decode($draft->files,true):[],'organizations'=>Organization::all(),'seminars'=>Event::where('type','seminar')->where('status','published')->where('starts_at','>',now())->get()]);
 }
 public function store(Request $r,string $kind){
  $this->requireCondition(array_key_exists($kind,ServiceRequest::kinds()),404);$this->requireCondition($r->user()->profile_complete,422,'Complete your profile first.');
  if(in_array($kind,['scholarship','leadership','activity','achievement','seminar','clearance']))$this->requireCondition($r->user()->account_type==='student',403);
  if($kind==='clearance'){$configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);if($configured)config(['portal.fields.clearance.schedule'=>'select:'.implode(',',array_unique(array_column($configured,'start')))]);}
  $rules=['declaration'=>'accepted'];$fields=config('portal.fields.'.$kind,[]);
  foreach($fields as $name=>$type){
   $rule='required|string|max:200';if($type==='textarea')$rule='required|string|min:10|max:5000';
   if($type==='date')$rule='required|date';
   if($type==='datetime-local')$rule='required|date|after_or_equal:today';
   if($type==='number')$rule='required|integer|min:1|max:'.($name==='points'?'1000':'5000');
   if(str_starts_with($type,'select:'))$rule='required|in:'.substr($type,7);
   if($type==='organization')$rule='required|integer|exists:organizations,id';
   if($type==='seminar')$rule='required|integer|exists:events,id';
   $rules['details.'.$name]=$rule;
  }
  foreach(config('portal.optional_fields.'.$kind,[]) as $name=>$type)$rules['details.'.$name]='nullable|string|max:500';
  if($kind==='clearance')$rules['details.appointment_date']='required|date|after:today';
  if($kind==='activity')$rules['details.ends_at']='required|date|after:details.starts_at';
  foreach(config('portal.documents.'.$kind,[]) as $key=>$label)$rules['documents.'.$key]=(in_array($kind.'.'.$key,config('portal.optional_documents'))?'nullable':'required').'|file|mimes:pdf,jpg,jpeg,png|max:'.($kind==='activity'?'10240':'5120');
  if($kind==='id-replacement'){
   $rules+=config('portal.id_replacement_payment_rules');
   $rules['documents.evidence']='required_if:details.replacement_reason,'.implode(',',config('portal.id_replacement_affidavit_reasons')).'|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
  }
  $v=$r->validate($rules,['documents.evidence.required_if'=>'Upload an affidavit of loss for a lost or stolen ID.'],['details.receipt_number'=>'receipt number','details.payment_amount'=>'payment amount']);$draft=$kind==='activity'?DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first():null;$paths=$draft?json_decode($draft->files,true):[];$newPaths=[];
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
     DB::table('settings')->insertOrIgnore(['key'=>'staff.clearance-slots','value'=>'[]']);$configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->value('value')??'[]',true);$slot=ServiceRequest::where('kind','clearance')->whereIn('status',['pending','in_review','unit_approved','awaiting_admin','approved'])->lockForUpdate()->get()->filter(fn($q)=>($q->details['appointment_date']??null)===$v['details']['appointment_date']&&($q->details['schedule']??null)===$v['details']['schedule'])->count();
     $capacity=10;if($configured){$chosen=$configured[$v['details']['appointment_date'].' '.$v['details']['schedule']]??null;$this->requireCondition($chosen&&($chosen['open']??false),422,'This office appointment is not available. Choose an open date and time.');$capacity=(int)$chosen['capacity'];}
     $this->rejectIf($slot>=$capacity,422,'This appointment slot is full. Choose another time.');
    }
    if($kind==='seminar'){
     $event=Event::lockForUpdate()->findOrFail($v['details']['event_id']);$this->requireCondition($event->type==='seminar'&&$event->status==='published'&&$event->starts_at->isFuture(),422);
    }
    $details=$v['details'];
    if(in_array($kind,['activity','achievement'],true))$details['organization_id']=$r->user()->organization_id;
    if($kind==='id-replacement')$details['student_name']=$r->user()->name;
    return ServiceRequest::create(['user_id'=>$r->user()->id,'ticket'=>strtoupper(substr($kind,0,3)).'-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),'kind'=>$kind,'details'=>$details,'files'=>$paths]);
   });
  }catch(\Throwable $e){foreach($newPaths as $p)Storage::delete($p);throw $e;}
  if($draft){DB::table('proposal_drafts')->where('id',$draft->id)->delete();foreach(json_decode($draft->files,true) as $key=>$p)if(($paths[$key]['path']??null)!==$p['path'])Storage::delete($p['path']);}
  \App\Support\StaffAlerts::role($q->office(),'New transaction: '.$q->ticket,'/workspace/request/'.$q->id,$kind==='activity'?'proposals':'submissions');
  return redirect('/requests/'.$q->id)->with('success','Your request has been submitted successfully.');
 }
 public function draft(Request $r){
  $this->requireCondition($r->user()->account_type==='student'&&$r->user()->profile_complete,403);
  $rules=[];foreach(config('portal.fields.activity') as $key=>$type)$rules['details.'.$key]='nullable|string|max:5000';
  foreach(config('portal.documents.activity') as $key=>$label)$rules['documents.'.$key]='nullable|file|mimes:pdf,jpg,jpeg,png|max:10240';
  $v=$r->validate($rules);$draft=DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first();$files=$draft?json_decode($draft->files,true):[];$new=[];$replaced=[];
  try{foreach($r->file('documents',[]) as $key=>$f)if(isset($rules['documents.'.$key])){if(isset($files[$key]))$replaced[]=$files[$key]['path'];$path=$f->store('requests');$new[]=$path;$files[$key]=['path'=>$path,'name'=>$f->getClientOriginalName()];}
   DB::table('proposal_drafts')->updateOrInsert(['user_id'=>$r->user()->id],['details'=>json_encode($v['details']??[]),'files'=>json_encode($files),'created_at'=>$draft?->created_at??now(),'updated_at'=>now()]);
  }catch(\Throwable $e){foreach($new as $p)Storage::delete($p);throw $e;}
  foreach($replaced as $p)Storage::delete($p);
  return redirect('/apply/activity')->with('success','Draft saved. Your proposal has not been submitted for review.');
 }
 public function show(Request $r,ServiceRequest $serviceRequest){$this->access($r,$serviceRequest);$history=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->where('audit_logs.subject',$serviceRequest->ticket)->where('audit_logs.action','like','request.%')->orderBy('audit_logs.id')->select('audit_logs.*','users.name as actor_name','users.role as actor_role')->get();return view('transactions.show',['q'=>$serviceRequest,'history'=>$history]);}
 public function cancel(Request $r,ServiceRequest $serviceRequest){
  $this->requireCondition($serviceRequest->user_id===$r->user()->id,403);
  $count=ServiceRequest::where('id',$serviceRequest->id)->whereIn('status',['pending','needs_revision'])->update(['status'=>'cancelled']);
  $this->requireCondition($count,422,'This request can no longer be cancelled.');DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'request.cancelled','subject'=>$serviceRequest->ticket,'created_at'=>now()]);return back()->with('success','Request cancelled.');
 }
 public function revise(Request $r,ServiceRequest $serviceRequest){
  $this->requireCondition($serviceRequest->user_id===$r->user()->id&&$serviceRequest->status==='needs_revision',403);
  $rules=['revision'=>'required|string|min:10|max:5000','documents.*'=>'nullable|file|mimes:'.(($serviceRequest->details['completion_report']??false)?'pdf,png,jpg,jpeg,docx':'pdf,png,jpg,jpeg').'|max:'.($serviceRequest->kind==='activity'?'10240':'5120')];
  if($serviceRequest->kind==='id-replacement'){
   foreach(config('portal.id_replacement_payment_rules') as $field=>$rule){
    $rules[$field]=filled(data_get($serviceRequest,$field))?'sometimes|'.$rule:$rule;
   }
   if(in_array($serviceRequest->details['replacement_reason']??null,config('portal.id_replacement_affidavit_reasons'),true)
    &&!$serviceRequest->attachmentPath('evidence'))$rules['documents.evidence']='required|file|mimes:pdf,jpg,jpeg,png|max:5120';
  }
  $v=$r->validate($rules,['documents.evidence.required'=>'Upload an affidavit of loss for a lost or stolen ID.'],['details.receipt_number'=>'receipt number','details.payment_amount'=>'payment amount']);
  $files=$serviceRequest->files??[];
  foreach($r->file('documents',[]) as $key=>$f){
   $this->requireCondition(array_key_exists($key,($serviceRequest->details['completion_report']??false)?['completion_report'=>'Completion Report']:config('portal.documents.'.$serviceRequest->kind,[])),422);
   $path=$f->store('requests');
   if(!$path)throw \Illuminate\Validation\ValidationException::withMessages(['documents.'.$key=>'The file could not be saved. Please upload it again.']);
   $files[$key]=['path'=>$path,'name'=>$f->getClientOriginalName()];
  }
  DB::transaction(function()use($serviceRequest,$r,$files,$v){
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);$this->requireCondition($q->status==='needs_revision',422);
   $details=[...$q->details,'revision'=>$r->revision];if($q->kind==='clearance'){unset($details['identity_verified'],$details['hymn_completed']);}
   if($q->kind==='id-replacement'){
    $details=array_replace($details,$v['details']??[]);
    $details['student_name']??=$q->user->name;
   }
   $q->update(['details'=>$details,'files'=>$files,'status'=>'pending']);DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'request.resubmitted','subject'=>$q->ticket,'created_at'=>now()]);\App\Support\StaffAlerts::role($q->office(),'Revised request: '.$q->ticket,'/workspace/request/'.$q->id,'resubmitted');
  });return back()->with('success','Revision sent for review.');
 }
 public function download(Request $r,ServiceRequest $serviceRequest,string $key){
  $file=$this->attachment($r,$serviceRequest,$key);
  return Storage::download($file['path'],$file['name']);
 }
 public function imagePreview(Request $r,ServiceRequest $serviceRequest,string $key){
  $file=$this->attachment($r,$serviceRequest,$key);
  abort_unless($serviceRequest->kind==='leadership',404);
  $mime=Storage::mimeType($file['path']);
  abort_unless(in_array($mime,['image/jpeg','image/png'],true),404);
  return $this->inlineAttachment($file,$mime);
 }
 public function viewAttachment(Request $r,ServiceRequest $serviceRequest,string $key){
  $file=$this->attachment($r,$serviceRequest,$key);
  $stream=Storage::readStream($file['path']);
  abort_unless(is_resource($stream),404);
  try {
   $sample=fread($stream,8192);
  } finally {
   fclose($stream);
  }
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($sample ?: '');
  if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true)){
   return Storage::download($file['path'],$file['name']);
  }
  return $this->inlineAttachment($file,$mime);
 }
 private function inlineAttachment(array $file,string $mime){
  return Storage::response($file['path'],$file['name'],[
   'Content-Type'=>$mime,
   'Cache-Control'=>'private, no-store',
   'X-Content-Type-Options'=>'nosniff',
  ]);
 }
 private function attachment(Request $r,ServiceRequest $serviceRequest,string $key):array {
  $this->access($r,$serviceRequest);
  $file=$serviceRequest->files[$key]??null;
  $path=$serviceRequest->attachmentPath($key);
  abort_unless($path!==null&&Storage::exists($path),404);
  return ['path'=>$path,'name'=>basename(is_array($file)?($file['name']??$path):$path)];
 }
 private function access(Request $r,ServiceRequest $q){$this->requireCondition($q->user_id===$r->user()->id||($r->user()->isStaff()&&(in_array($r->user()->role,['admin','assistant'])||$q->office()===$r->user()->role)),403);}
}

