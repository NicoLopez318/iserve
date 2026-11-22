<?php
namespace App\Http\Controllers;
use App\Models\{User,ServiceRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class ScholarOnboardingController extends Controller {
 private function student(Request $request):User{
  $user=$request->user();abort_unless(!$user->isStaff()&&$user->account_type==='student',403);return $user;
 }
 private function openApplication(User $user){return $user->requests()->where('kind','scholarship')->whereIn('status',['pending','in_review','unit_approved','awaiting_admin','needs_revision'])->latest()->first();}
 private function applicationUrl(ServiceRequest $application):string{if(in_array($application->status,['unit_approved','awaiting_admin','approved']))return '/portal/scholar';return $application->status==='needs_revision'?'/requests/'.$application->id:'/scholar/submitted/'.$application->id;}
 public function form(Request $request){
  if($this->student($request)->hasScholarPortalAccess())return redirect('/portal/scholar');
  $user=$this->student($request);if($pending=$this->openApplication($user))return redirect($this->applicationUrl($pending));
  $courses=User::whereNotNull('course')->where('course','<>','')->distinct()->orderBy('course')->pluck('course');
  return view('transactions.scholar-information',compact('courses'));
 }
 public function next(Request $request){
  $user=$this->student($request);if($pending=$this->openApplication($user))return redirect($this->applicationUrl($pending));
  $courses=User::whereNotNull('course')->where('course','<>','')->distinct()->pluck('course')->all();
  $validated=$request->validate([
   'name'=>'required|string|max:120',
   'student_id'=>['required','string','max:40',Rule::unique('users')->ignore($user->id)],
   'course'=>['required','string','max:100',Rule::in($courses)],
   'year_level'=>'required|string|max:30',
  ]);
  DB::transaction(function()use($user,$validated){User::lockForUpdate()->findOrFail($user->id)->update([...$validated,'profile_complete'=>true]);});
  return redirect('/apply/scholarship');
 }
 public function documents(Request $request){
  $user=$this->student($request);if($pending=$this->openApplication($user))return redirect($this->applicationUrl($pending));
  if(!$user->profile_complete||!$user->student_id||!$user->course||!$user->year_level)return redirect('/scholar/information');
  return view('transactions.scholar-documents');
 }
 public function store(Request $request){
  $user=$this->student($request);
  abort_unless($user->profile_complete&&$user->student_id&&$user->course&&$user->year_level,422,'Complete your Scholar Information first.');
  $request->validate([
   'documents.application'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
   'documents.photo'=>'required|image|mimes:jpg,jpeg,png|max:5120',
   'documents.registration'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
  ]);
  $files=[];
  try{
   foreach(['application','photo','registration'] as $key){$file=$request->file('documents.'.$key);$files[$key]=['path'=>$file->store('requests'),'name'=>$file->getClientOriginalName()];}
   $application=DB::transaction(function()use($user,$files){
    $owner=User::lockForUpdate()->findOrFail($user->id);
    abort_if($this->openApplication($owner),422,'Your scholarship application is already awaiting review.');
    $settings=DB::table('settings')->whereIn('key',['academic_year','semester'])->pluck('value','key');
    $application=ServiceRequest::create([
     'user_id'=>$owner->id,'kind'=>'scholarship','status'=>'pending',
     'ticket'=>'SCH-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
     'details'=>['full_name'=>$owner->name,'student_id'=>$owner->student_id,'course'=>$owner->course,'year_level'=>$owner->year_level,'academic_year'=>$settings['academic_year']??now()->year.'–'.(now()->year+1),'semester'=>$settings['semester']??'1st Semester'],
     'files'=>$files,
    ]);
    if($owner->preferences['in_app']??true)$owner->notifications()->create(['id'=>(string)Str::uuid(),'type'=>'request','data'=>json_encode(['title'=>'Scholarship application received: '.$application->ticket,'message'=>'Your documents have been submitted to the Scholarship and Financial Unit for review.','url'=>'/scholar/submitted/'.$application->id])]);
    \App\Support\StaffAlerts::role('sfu_head','New scholarship application: '.$application->ticket,'/workspace/applications?review='.$application->id);
    return $application;
   });
  }catch(\Throwable $error){foreach($files as $file)Storage::delete($file['path']);throw $error;}
  return redirect('/scholar/submitted/'.$application->id);
 }
 public function submitted(Request $request,ServiceRequest $serviceRequest){
  abort_unless((int)$serviceRequest->user_id===(int)$request->user()->id&&$serviceRequest->kind==='scholarship',403);
  if($request->user()->hasScholarPortalAccess())return redirect('/portal/scholar');
  if(!in_array($serviceRequest->status,['pending','in_review','unit_approved','awaiting_admin']))return redirect('/requests/'.$serviceRequest->id);
  return view('transactions.scholar-submitted',['application'=>$serviceRequest]);
 }
}
