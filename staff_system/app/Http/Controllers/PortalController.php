<?php
namespace App\Http\Controllers;
use App\Models\{User,ServiceRequest,Assignment,Attendance,Journal,Event,Organization,Point,Post};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PortalController extends Controller {
 public function home(Request $r){return view('portal.home',['requests'=>$r->user()->requests()->latest()->get()]);}
 public function page(Request $r,string $portal,string $page='dashboard'){
  $this->requireCondition(in_array($portal,['values','leadership','scholar']),404);
  $this->requireCondition(in_array($page,['dashboard','violations','community-service','journal','profile','settings','events','organization','members','leaderboards','propose','archives','tuition']),404);
  $this->requireCondition($r->user()->account_type==='student'||$r->user()->isStaff(),403);
  $u=$r->user();
  $events=Event::where('status','published')->orderBy('starts_at')->get();
  $attendances=Attendance::where('user_id',$u->id)->with('event')->latest()->get();
  $assignments=collect();
  $assignment=null;
  $openServiceSession=null;
  if($portal==='values'){
   $assignments=$u->assignments()->withSum(['attendances as verified_minutes'=>fn($query)=>$query->where('status','approved')],'minutes')->latest('id')->get();
   $active=$assignments->where('status','active');
   $completed=$assignments->where('status','completed');
   $openServiceSession=$attendances->first(fn($entry)=>$entry->status==='in_progress'&&$entry->time_in&&!$entry->time_out&&$active->contains('id',$entry->assignment_id));
   if($r->filled('assignment')){
    $r->validate(['assignment'=>'integer|min:1']);
    $assignment=$assignments->firstWhere('id',$r->integer('assignment'));
    abort_unless($assignment,404);
   }else{
    $assignment=$active->firstWhere('id',$openServiceSession?->assignment_id)??$active->first()??$completed->first();
   }
  }
  $minutes=(int)($assignment?->verified_minutes??0);
  if($page==='community-service')$assignment?->load('feedbackEntries.author');
  $requests=$u->requests()->latest()->get();
  $leaders=User::where('account_type','student')->select('users.*')->selectSub(Point::selectRaw('COALESCE(SUM(amount),0)')->whereColumn('points.user_id','users.id'),'xp')->orderByDesc('xp')->limit(50)->get();
  $xp=Point::where('user_id',$u->id)->sum('amount');
  $organization=$u->organization;
  $posts=$organization?->posts()->with(['user','comments.user'])->latest()->get()??collect();
  $scholarship=$requests->first(fn($q)=>$q->kind==='scholarship'&&$q->status==='approved');
  $settings=DB::table('settings')->pluck('value','key');
  $fees=['Tuition Fee'=>(int)($settings['tuition']??2000000),'Laboratory Fee'=>(int)($settings['laboratory']??500000),'Miscellaneous Fee'=>(int)($settings['miscellaneous']??350000)];
  $rate=(int)($scholarship?->details['discount_rate']??0);$total=array_sum($fees);$discount=array_sum(array_map(fn($cents)=>(int)round($cents*$rate/100),$fees));
  return view('portal.page',compact('portal','page','u','assignments','assignment','openServiceSession','minutes','events','attendances','requests','leaders','xp','organization','posts','scholarship','fees','rate','total','discount'));
 }
 public function post(Request $r){
  $r->validate(['body'=>'required|string|min:3|max:3000']);$this->requireCondition($r->user()->organization_id,403);
  Post::create(['organization_id'=>$r->user()->organization_id,'user_id'=>$r->user()->id,'body'=>$r->body]);return back()->with('success','Post published.');
 }
 public function moderators(Request $r){
  $u=$r->user();$org=$u->organization;$this->requireCondition($org,422,'Join an organization first.');
  $this->requireCondition(in_array($u->role,['admin','slu_head'])||strtolower(trim($u->position??''))==='president',403);
  $v=$r->validate(['moderators'=>'nullable|array|max:10','moderators.*'=>'integer|distinct']);$ids=$v['moderators']??[];
  $this->requireCondition($org->members()->whereIn('id',$ids)->count()===count($ids),422,'Choose members of your organization.');
  $org->update(['moderators'=>array_map('intval',$ids)]);return back()->with('success','Moderators updated.');
 }
 public function comment(Request $r,Post $post){
  $this->requireCondition($post->organization_id===$r->user()->organization_id,403);$r->validate(['body'=>'required|string|max:1000']);
  $post->comments()->create(['user_id'=>$r->user()->id,'body'=>$r->body]);return back()->with('success','Comment added.');
 }
 private function activeServiceAssignment(Request $r, ?int $openAssignmentId=null):Assignment
 {
  $active=$r->user()->assignments()->where('status','active')->lockForUpdate();
  $id=$r->integer('assignment_id')?:$openAssignmentId;
  if($id)return $active->findOrFail($id);

  $available=$active->limit(2)->get();
  $this->requireCondition($available->count()===1,422,$available->isEmpty()
   ?'No active community service record.':'Select the violation for this community service record.');
  return $available->first();
 }
 public function journal(Request $r){
  $r->validate(['assignment_id'=>'nullable|integer|min:1','module'=>'required|integer|between:1,4','answer'=>'required|string|min:30|max:10000']);
  DB::transaction(function()use($r){
   $a=$this->activeServiceAssignment($r);
   $required=[1=>0,2=>(int)ceil($a->required_minutes/3),3=>(int)ceil($a->required_minutes*2/3),4=>$a->required_minutes];
   $this->requireCondition($a->approvedMinutes()>=$required[$r->integer('module')],422,'This module is still locked.');
   $j=Journal::firstOrNew(['assignment_id'=>$a->id,'module'=>$r->integer('module')]);$this->rejectIf($j->exists&&$j->status==='approved',422,'An approved journal cannot be changed.');
   $j->fill(['answer'=>$r->answer,'status'=>'pending'])->save();
  });
  return back()->with('success','Journal sent for review.');
 }
 public function clock(Request $r){
  $r->validate(['assignment_id'=>'nullable|integer|min:1','action'=>'required|in:in,out','photo'=>'required|image|max:5120']);
  DB::transaction(function()use($r){
   // Serialize clocks across all assignments for this student.
   User::lockForUpdate()->findOrFail($r->user()->id);
   $open=Attendance::where('user_id',$r->user()->id)->whereNotNull('assignment_id')
    ->whereNotNull('time_in')->whereNull('time_out')->where('status','in_progress')
    ->whereHas('assignment',fn($query)=>$query->where('status','active'))->lockForUpdate()->first();
   $a=$this->activeServiceAssignment($r,$r->action==='out'?$open?->assignment_id:null);
   if($r->action==='in'){
    $this->rejectIf($open,422,'Time out of your active duty session before starting another.');
    $this->rejectIf(Attendance::where('assignment_id',$a->id)->whereDate('time_in',today())->exists(),422,'Only one duty session per violation is allowed each day.');
    Attendance::create(['assignment_id'=>$a->id,'user_id'=>$r->user()->id,'time_in'=>now(),'photo_in'=>$r->file('photo')->store('attendance'),'status'=>'in_progress']);
   } else {
    $this->requireCondition($open,422,'Time in before timing out.');
    $this->requireCondition((int)$open->assignment_id===$a->id,422,'Time out from the same violation you timed in for.');
    $mins=min(480,max(0,(int)$open->time_in->diffInMinutes(now())));
    $open->update(['time_out'=>now(),'minutes'=>$mins,'photo_out'=>$r->file('photo')->store('attendance'),'status'=>'pending']);
   }
  });return back()->with('success',$r->action==='in'?'Time in recorded.':'Time out recorded. Hours are awaiting staff verification.');
 }
 public function event(Request $r,Event $event){
  $r->validate(['action'=>'required|in:join,in,out,reflection','photo'=>'required_if:action,in,out|nullable|image|mimes:png,jpg,jpeg,webp|max:5120','reflection'=>'required_if:action,reflection|nullable|string|min:30|max:5000']);
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
    $a->update(['time_in'=>now(),'photo_in'=>$r->file('photo')->store('attendance'),'status'=>'in_progress']);
   }elseif($r->action==='out'){
    $this->requireCondition($a->time_in&&!$a->time_out,422);$this->requireCondition(now()->between($event->starts_at,$event->ends_at->copy()->addHour()),422,'The attendance window has closed.');
    $a->update(['time_out'=>now(),'photo_out'=>$r->file('photo')->store('attendance'),'minutes'=>max(0,(int)$a->time_in->diffInMinutes(now())),'status'=>'awaiting_reflection']);
   }else {
    $this->requireCondition($a->time_out&&in_array($a->status,['awaiting_reflection','rejected']),422,'Complete attendance first.');
    $a->update(['reflection'=>$r->reflection,'status'=>'pending']);
   }
  });return back()->with('success','Event participation updated.');
 }
 public function reviseAttendance(Request $r,Attendance $attendance){
  $this->requireCondition($attendance->user_id===$r->user()->id&&$attendance->assignment_id,403);
  $r->validate(['photo'=>'required|image|max:5120','explanation'=>'required|string|min:10|max:3000']);
  DB::transaction(function()use($r,$attendance){$a=Attendance::lockForUpdate()->findOrFail($attendance->id);$this->requireCondition($a->status==='rejected',422,'Only rejected duty sessions may be resubmitted.');$a->update(['photo_out'=>$r->file('photo')->store('attendance'),'reflection'=>$r->explanation,'status'=>'pending']);});return back()->with('success','Evidence resubmitted for verification.');
 }
}

