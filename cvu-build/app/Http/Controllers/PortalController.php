<?php
namespace App\Http\Controllers;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event,Organization,Point,Post};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PortalController extends Controller {
 public function home(Request $r){return redirect($r->query('choose')==='lost-found'?'/lost-found':'/');}
 public function page(Request $r,string $portal,string $page='dashboard'){
  $this->requireCondition(in_array($portal,['values','leadership','scholar']),404);
  $this->requireCondition(in_array($page,['dashboard','violations','community-service','journal','profile','settings','events','organization','members','leaderboards','propose','archives','tuition']),404);
  $this->requireCondition($r->user()->account_type==='student'||$r->user()->isStaff(),403);
  $u=$r->user();
  if($portal==='values'){$a=$u->assignment;if(!$u->values_verified_at||!$a||!in_array($a->status,['active','completed']))return redirect('/community-link');}

  if($portal==='scholar'&&!$u->isStaff()&&!$u->hasScholarPortalAccess())return redirect('/scholar/information');
  if($portal==='leadership'&&!$u->isStaff()&&!$u->organization_id){
   $pending=$u->requests()->where('kind','leadership')->whereIn('status',['pending','in_review','unit_approved','awaiting_admin'])->latest()->first();
   return redirect($pending?'/leadership/submitted/'.$pending->id:'/apply/leadership');
  }
  $assignment=$u->assignment;$minutes=$assignment?->approvedMinutes()??0;
  $events=Event::where('status','published')->orderBy('starts_at')->get();
  $attendances=Attendance::where('user_id',$u->id)->with('event')->latest()->get();
  $requests=$u->requests()->latest()->get();
  $leaders=User::where('account_type','student')->select('users.*')->selectSub(Point::selectRaw('COALESCE(SUM(amount),0)')->whereColumn('points.user_id','users.id'),'xp')->orderByDesc('xp')->limit(50)->get();
  $xp=Point::where('user_id',$u->id)->sum('amount');
  $organization=$u->organization;
  $posts=$organization?->posts()->with(['user','comments.user'])->latest()->get()??collect();
  $scholarship=$requests->first(fn($q)=>$q->kind==='scholarship'&&$q->status==='approved');
  $scholarEnrollment=$requests->first(fn($q)=>$q->kind==='scholarship'&&in_array($q->status,['unit_approved','awaiting_admin','approved']));
  $settings=DB::table('settings')->pluck('value','key');
  $fees=['Tuition Fee'=>(int)($settings['tuition']??2000000),'Laboratory Fee'=>(int)($settings['laboratory']??500000),'Miscellaneous Fee'=>(int)($settings['miscellaneous']??350000)];
  $rate=(int)($scholarship?->details['discount_rate']??0);$total=array_sum($fees);$discount=array_sum(array_map(fn($cents)=>(int)round($cents*$rate/100),$fees));
  return view('portal.page',compact('portal','page','u','assignment','minutes','events','attendances','requests','leaders','xp','organization','posts','scholarship','scholarEnrollment','fees','rate','total','discount'));
 }
 public function post(Request $r){
  $r->validate(['body'=>'required|string|min:3|max:3000']);$this->requireCondition($r->user()->organization_id,403);
  Post::create(['organization_id'=>$r->user()->organization_id,'user_id'=>$r->user()->id,'body'=>$r->body]);return back()->with('success','Post published.');
 }
 public function moderators(Request $r){
  $u=$r->user();

  $org=$u->organization;$this->requireCondition($org,422,'Join an organization first.');
  $this->requireCondition(in_array($u->role,['admin','slu_head'])||strtolower(trim($u->position??''))==='president',403);
  $v=$r->validate(['moderators'=>'nullable|array|max:10','moderators.*'=>'integer|distinct']);$ids=$v['moderators']??[];
  $this->requireCondition($org->members()->whereIn('id',$ids)->count()===count($ids),422,'Choose members of your organization.');
  $org->update(['moderators'=>array_map('intval',$ids)]);return back()->with('success','Moderators updated.');
 }
 public function comment(Request $r,Post $post){
  $this->requireCondition($post->organization_id===$r->user()->organization_id,403);$r->validate(['body'=>'required|string|max:1000']);
  $post->comments()->create(['user_id'=>$r->user()->id,'body'=>$r->body]);return back()->with('success','Comment added.');
 }
 public function journal(Request $r){
  $r->validate(['module'=>'required|integer|between:1,4','answer'=>'required|string|min:30|max:10000']);
  $a=$r->user()->assignment;$this->requireCondition($a&&$a->status==='active'&&$r->user()->values_verified_at,422);
  $required=[1=>0,2=>(int)ceil($a->required_minutes/3),3=>(int)ceil($a->required_minutes*2/3),4=>$a->required_minutes];
  $this->requireCondition($a->approvedMinutes()>=$required[$r->integer('module')],422,'This module is still locked.');
  $j=Journal::firstOrNew(['assignment_id'=>$a->id,'module'=>$r->integer('module')]);$this->rejectIf($j->exists&&$j->status==='approved',422,'An approved journal cannot be changed.');
  $resubmitted=$j->exists&&$j->status==='rejected';
  $j->fill(['answer'=>$r->answer,'status'=>'pending'])->save();
  if($j->wasRecentlyCreated||$resubmitted){
   \App\Support\StaffAlerts::role('cvu_head','Duty journal ready to review','/workspace/community#duty-journal',$resubmitted?'resubmitted':'submissions');
  }
  return back()->with('success','Journal sent for review.');
 }
 public function clock(Request $r){
  $r->validate(['action'=>'required|in:in,out','photo'=>'required|image|max:5120']);
  DB::transaction(function()use($r){
   $a=Assignment::where('user_id',$r->user()->id)->lockForUpdate()->latest('id')->first();$this->requireCondition($a&&$a->status==='active'&&$r->user()->values_verified_at,422,'A confirmed Student ID and active service record are required.');
   $open=Attendance::where('assignment_id',$a->id)->whereNull('time_out')->lockForUpdate()->first();
   if($r->action==='in'){
    $this->rejectIf($open,422,'You already have an active session.');
    $this->rejectIf(Attendance::where('assignment_id',$a->id)->whereDate('time_in',today())->exists(),422,'Only one duty session is allowed each day.');
    $photo=$r->file('photo')->store('attendance');
    $this->requireCondition($photo,422,'The Time In photo could not be saved. Please try again.');
    Attendance::create(['assignment_id'=>$a->id,'user_id'=>$r->user()->id,'time_in'=>now(),'photo_in'=>$photo,'status'=>'in_progress']);
   } else {
    $this->requireCondition($open,422,'Time in before timing out.');
    $mins=min(480,max(0,(int)$open->time_in->diffInMinutes(now())));
    $photo=$r->file('photo')->store('attendance');
    $this->requireCondition($photo,422,'The Time Out photo could not be saved. Please try again.');
    $open->update(['time_out'=>now(),'minutes'=>$mins,'photo_out'=>$photo,'status'=>'pending']);
    \App\Support\StaffAlerts::role('cvu_head','Community service attendance ready to verify','/workspace/community#attendance-verification');
   }
  });return back()->with('success',$r->action==='in'?'Time in recorded.':'Time out recorded. Hours are awaiting staff verification.');
 }
 public function event(Request $r,Event $event){
  if($event->type==='seminar'&&$r->input('action')==='join')return redirect('/apply/seminar?event='.$event->id);
  if($event->type==='leadership')$this->requireCondition($r->user()->organization_id,403,'Your SLU membership must be accepted first.');
  $r->validate(['action'=>'required|in:join,in,out,reflection','reflection'=>'required_if:action,reflection|nullable|string|min:30|max:5000']);
  DB::transaction(function()use($r,$event){
   $event=Event::lockForUpdate()->findOrFail($event->id);$this->requireCondition($event->status==='published',422);
   $a=Attendance::where('user_id',$r->user()->id)->where('event_id',$event->id)->first();
   if($r->action==='join'){
    $this->rejectIf($a,422,'Already registered.');$this->rejectIf($event->starts_at->isPast(),422,'Registration has closed.');$this->rejectIf($event->attendances()->count()>=$event->capacity,422,'This event is full.');
    Attendance::create(['user_id'=>$r->user()->id,'event_id'=>$event->id]);return;
   }
   $this->requireCondition($a,422,'Register first.');
   if($r->action==='in'){
    $this->rejectIf($a->time_in,422,'Time in already recorded.');$this->requireCondition(now()->between($event->starts_at,$event->ends_at),422,'Attendance is available during the event.');
    $a->update(['time_in'=>now(),'status'=>'in_progress']);
   }elseif($r->action==='out'){
    $this->requireCondition($a->time_in&&!$a->time_out,422);$this->requireCondition(now()->between($event->starts_at,$event->ends_at->copy()->addHour()),422,'The attendance window has closed.');
    $a->update(['time_out'=>now(),'minutes'=>max(0,(int)$a->time_in->diffInMinutes(now())),'status'=>'awaiting_reflection']);
   }else {
    $this->requireCondition($a->time_out&&in_array($a->status,['awaiting_reflection','rejected']),422,'Complete attendance first.');
    $resubmitted=$a->status==='rejected';
    $a->update(['reflection'=>$r->reflection,'status'=>'pending']);
    \App\Support\StaffAlerts::role('slu_head','Event attendance ready to verify: '.$event->title,'/workspace/events#event-'.$event->id.'-attendance',
     $resubmitted?'event_resubmitted':'event_updates');
   }
  });return back()->with('success','Event participation updated.');
 }
 public function reviseAttendance(Request $r,Attendance $attendance){
  $assignment=$r->user()->assignment;
  $this->requireCondition($assignment&&$assignment->status==='active'&&$r->user()->values_verified_at,403,'A confirmed Student ID and active service record are required.');
  $this->requireCondition($attendance->user_id===$r->user()->id&&$attendance->assignment_id,403);
  $r->validate(['photo'=>'required|image|max:5120','explanation'=>'required|string|min:10|max:3000']);
  DB::transaction(function()use($r,$attendance){$a=Attendance::lockForUpdate()->findOrFail($attendance->id);$this->requireCondition($a->status==='rejected',422,'Only rejected duty sessions may be resubmitted.');$a->update(['photo_out'=>$r->file('photo')->store('attendance'),'reflection'=>$r->explanation,'status'=>'pending']);
   \App\Support\StaffAlerts::role('cvu_head','Community service evidence resubmitted','/workspace/community#attendance-verification','resubmitted');
  });return back()->with('success','Evidence resubmitted for verification.');
 }
}

