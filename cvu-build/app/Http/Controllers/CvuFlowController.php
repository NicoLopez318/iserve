<?php
namespace App\Http\Controllers;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event};
use App\Support\TransactionEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class CvuFlowController extends Controller {
 const YEARS=['1st Year','2nd Year','3rd Year','4th Year'];
 private function requireFlow(bool $condition,string $message,string $field='workflow'):void {if(!$condition)throw ValidationException::withMessages([$field=>$message]);}
 private function slotOpen(?array $slot):bool {return $slot&&($slot['open']??false)&&\Carbon\Carbon::parse($slot['date'].' '.$slot['start'])->isFuture();}
 private function student(Request $r):User {abort_unless($r->user()->account_type==='student'&&!$r->user()->isStaff(),403);return $r->user();}
 public function form(Request $r,string $kind){
  $u=$this->student($r);$courses=User::whereNotNull('course')->where('course','<>','')->distinct()->orderBy('course')->pluck('course');
  $events=Event::where('type','seminar')->where('status','published')->where('ends_at','>',now())->with(['attendances'=>fn($q)=>$q->where('user_id',$u->id)->where('status','<>','cancelled')])->withCount(['attendances as registration_count'=>fn($q)=>$q->where('status','<>','cancelled')])->orderBy('starts_at')->get();
  $slots=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->value('value')??'[]',true);
  return view('cvu.student',compact('kind','u','courses','events','slots'));
 }
 private function identity(Request $r):array {return $r->validate(['name'=>'required|string|max:120','student_id'=>['required','string','max:40',Rule::unique('users')->ignore($r->user()->id)],'course'=>'required|string|max:100','year_level'=>['required',Rule::in(self::YEARS)],'contact'=>'required|string|max:40']);}
 public function submit(Request $r,string $kind){
  $u=$this->student($r);$identity=$this->identity($r);$r->validate(['confirmations'=>'required|array|min:'.($kind==='readmission'?2:3),'confirmations.*'=>'accepted']);
  $rules=match($kind){
   'readmission'=>['details.instructor'=>'required|string|max:120','details.subject'=>'required|string|max:120','details.subject_code'=>'required|string|max:40','details.schedule'=>'required|string|max:120','details.semester'=>'required|string|max:40','details.academic_year'=>'required|string|max:40','details.description'=>'required|string|min:10|max:5000','evidence'=>'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'],
   'clearance'=>['details.appointment_slot'=>'required|string|max:100'],
   'seminar'=>['details.event_id'=>'required|integer|exists:events,id'],default=>abort(404)};
  $v=$r->validate($rules);$path=null;
  try{
   if($r->hasFile('evidence')){
    $path=$r->file('evidence')->store('requests');
    $this->requireFlow((bool)$path,'The evidence could not be saved. Please upload it again.','evidence');
   }
   $q=DB::transaction(function()use($r,$u,$kind,$identity,$v,$path){
    $owner=User::lockForUpdate()->findOrFail($u->id);
    $this->requireFlow($kind==='seminar'||!ServiceRequest::where('user_id',$u->id)->where('kind',$kind)->whereNotIn('status',['approved','rejected','cancelled','released'])->exists(),'You already have an open request of this type.');
    $details=[...$v['details'],'applicant'=>$identity];
    if($kind==='clearance'){
     $this->requireFlow(!Assignment::where('user_id',$u->id)->where('status','active')->exists(),'Complete your community service and final verification first.');
     $configured=json_decode(DB::table('settings')->where('key','staff.clearance-slots')->lockForUpdate()->value('value')??'[]',true);$slot=$configured[$details['appointment_slot']]??null;
     $this->requireFlow($this->slotOpen($slot),'Choose an available future appointment.','details.appointment_slot');
     $count=ServiceRequest::where('kind','clearance')->whereNotIn('status',['rejected','cancelled'])->get()->filter(fn($q)=>($q->details['appointment_slot']??(($q->details['appointment_date']??'').' '.($q->details['schedule']??'')))===$details['appointment_slot'])->count();
     $this->requireFlow($count<(int)$slot['capacity'],'This slot is full.','details.appointment_slot');$details['appointment_date']=$slot['date'];$details['schedule']=$slot['start'];$details['appointment_status']='pending';
    }
    if($kind==='seminar'){
     $event=Event::lockForUpdate()->findOrFail($details['event_id']);$meta=$event->metadata??[];
     $this->requireFlow($event->type==='seminar'&&$event->status==='published'&&$event->ends_at->isFuture(),'Seminar is not open.','details.event_id');
     $this->requireFlow(empty($meta['deadline'])||now()->lte(\Carbon\Carbon::parse($meta['deadline'])->endOfDay()),'Registration has closed.','details.event_id');
     $this->requireFlow(empty($meta['courses'])||in_array($identity['course'],$meta['courses']),'Your course is not eligible.','details.event_id');
     $this->requireFlow(empty($meta['years'])||in_array($identity['year_level'],$meta['years']),'Your year is not eligible.','details.event_id');
     $this->requireFlow(!$event->attendances()->where('user_id',$u->id)->where('status','<>','cancelled')->exists(),'You already joined this seminar.','details.event_id');
     $this->requireFlow($event->attendances()->where('status','<>','cancelled')->count()<$event->capacity,'Seminar is full.','details.event_id');
     Attendance::updateOrCreate(['event_id'=>$event->id,'user_id'=>$u->id],['status'=>'pending','reflection'=>'Student registered; awaiting facilitator attendance verification.']);
    }
    $owner->update(['name'=>$identity['name'],'student_id'=>$identity['student_id'],'course'=>$identity['course'],'year_level'=>$identity['year_level'],'phone'=>$identity['contact'],'profile_complete'=>true]);
    $request=ServiceRequest::create(['user_id'=>$u->id,'kind'=>$kind,'ticket'=>($kind==='readmission'?'RA':($kind==='clearance'?'CLR':'SEM')).'-'.now()->year.'-'.strtoupper(Str::random(8)),'details'=>$details,'files'=>$path?['evidence'=>['path'=>$path,'name'=>$r->file('evidence')->getClientOriginalName()]]:[]]);
    TransactionEmail::receipt($request);
    return $request;
   });
  }catch(\Throwable $e){if($path)Storage::delete($path);throw $e;}
  \App\Support\StaffAlerts::role('cvu_head','New '.$kind.' request: '.$q->ticket,'/workspace/request/'.$q->id);
  return redirect('/cvu/submitted/'.$q->id);
 }
 public function submitted(Request $r,ServiceRequest $serviceRequest){abort_unless($serviceRequest->user_id===$r->user()->id,403);return view('cvu.success',['q'=>$serviceRequest]);}
 public function community(Request $r){
  $u=$this->student($r);
  $a=$u->assignments()->whereIn('status',['active','completed'])->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->latest('id')->first();
  return view('cvu.community',compact('u','a'));
 }
 public function confirmStudent(Request $r){
  $u=$this->student($r);
  $v=$r->validate(['student_id'=>'required|string|max:40']);
  if(!filled($u->student_id)||!hash_equals(trim((string)$u->student_id),trim($v['student_id']))) {
   throw \Illuminate\Validation\ValidationException::withMessages(['student_id'=>'Use the Student ID registered to your account.']);
  }
  $u->update(['values_verified_at'=>now()]);
  $r->session()->forget('cvu.code');
  return redirect('/community-link?linked=1');
 }
}
