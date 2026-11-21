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
  $v=$r->validate($rules);$draft=$kind==='activity'?DB::table('proposal_drafts')->where('user_id',$r->user()->id)->first():null;$paths=$draft?json_decode($draft->files,true):[];$newPaths=[];
  try{
   foreach($r->file('documents',[]) as $key=>$f)if(isset($rules['documents.'.$key])){$paths[$key]=['path'=>$f->store('requests'),'name'=>$f->getClientOriginalName()];$newPaths[]=$paths[$key]['path'];}
   $q=DB::transaction(function()use($r,$kind,$v,$paths){
    // Lock the owner to serialize duplicate submissions.
    DB::table('users')->where('id',$r->user()->id)->update(['updated_at'=>now()]);
    $this->rejectIf(in_array($kind,['scholarship','leadership','clearance'])&&ServiceRequest::where('user_id',$r->user()->id)->where('kind',$kind)->whereIn('status',['pending','in_review','needs_revision','unit_approved','awaiting_admin'])->exists(),422,'You already have an open request of this type.');
    if($kind==='clearance'){
     $a=$r->user()->assignment;$this->rejectIf($a&&$a->status!=='completed',422,'Finish your community service and final verification before requesting clearance.');
     DB::table('settings')->insertOrIgnore(['key'=>'staff.clearance-slots','value'=>'[]']);$configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->value('value')??'[]',true);$slot=ServiceRequest::where('kind','clearance')->whereIn('status',['pending','in_review','unit_approved','awaiting_admin','approved'])->lockForUpdate()->get()->filter(fn($q)=>($q->details['appointment_date']??null)===$v['details']['appointment_date']&&($q->details['schedule']??null)===$v['details']['schedule'])->count();
     $capacity=10;if($configured){$chosen=$configured[$v['details']['appointment_date'].' '.$v['details']['schedule']]??null;$this->requireCondition($chosen&&($chosen['open']??false),422,'This office appointment is not available. Choose an open date and time.');$capacity=(int)$chosen['capacity'];}
     $this->rejectIf($slot>=$capacity,422,'This appointment slot is full. Choose another time.');
    }
    if($kind==='seminar'){
     $event=Event::lockForUpdate()->findOrFail($v['details']['event_id']);$this->requireCondition($event->type==='seminar'&&$event->status==='published'&&$event->starts_at->isFuture(),422);
    }
    return ServiceRequest::create(['user_id'=>$r->user()->id,'ticket'=>strtoupper(substr($kind,0,3)).'-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),'kind'=>$kind,'details'=>$v['details'],'files'=>$paths]);
   });
  }catch(\Throwable $e){foreach($newPaths as $p)Storage::delete($p);throw $e;}
  if($draft){DB::table('proposal_drafts')->where('id',$draft->id)->delete();foreach(json_decode($draft->files,true) as $key=>$p)if(($paths[$key]['path']??null)!==$p['path'])Storage::delete($p['path']);}
  \App\Support\StaffAlerts::role($q->office(),'New transaction: '.$q->ticket,'/workspace/request/'.$q->id);
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
  $v=$r->validate(['revision'=>'required|string|min:10|max:5000','documents.*'=>'nullable|file|mimes:'.(($serviceRequest->details['completion_report']??false)?'pdf,png,jpg,jpeg,docx':'pdf,png,jpg,jpeg').'|max:'.($serviceRequest->kind==='activity'?'10240':'5120')]);
  $files=$serviceRequest->files??[];
  foreach($r->file('documents',[]) as $key=>$f){$this->requireCondition(array_key_exists($key,($serviceRequest->details['completion_report']??false)?['completion_report'=>'Completion Report']:config('portal.documents.'.$serviceRequest->kind,[])),422);$files[$key]=['path'=>$f->store('requests'),'name'=>$f->getClientOriginalName()];}
  DB::transaction(function()use($serviceRequest,$r,$files){
   $q=ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);$this->requireCondition($q->status==='needs_revision',422);
   $details=[...$q->details,'revision'=>$r->revision];if($q->kind==='clearance'){unset($details['identity_verified'],$details['hymn_completed']);}$q->update(['details'=>$details,'files'=>$files,'status'=>'pending']);DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'request.resubmitted','subject'=>$q->ticket,'created_at'=>now()]);\App\Support\StaffAlerts::role($q->office(),'Revised request: '.$q->ticket,'/workspace/request/'.$q->id,'resubmitted');
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
  return Storage::response($file['path'],$file['name'],[
   'Content-Type'=>$mime,
   'Cache-Control'=>'private, no-store',
   'X-Content-Type-Options'=>'nosniff',
  ]);
 }
 private function attachment(Request $r,ServiceRequest $serviceRequest,string $key):array {
  $this->access($r,$serviceRequest);
  $file=$serviceRequest->files[$key]??null;
  $path=is_array($file)?($file['path']??null):$file;
  abort_unless(is_string($path)&&$path!==''&&Storage::exists($path),404);
  return ['path'=>$path,'name'=>basename(is_array($file)?($file['name']??$path):$path)];
 }
 private function access(Request $r,ServiceRequest $q){$this->requireCondition($q->user_id===$r->user()->id||($r->user()->isStaff()&&(in_array($r->user()->role,['admin','assistant'])||$q->office()===$r->user()->role)),403);}
}

