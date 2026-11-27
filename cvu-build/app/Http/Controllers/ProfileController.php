<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class ProfileController extends Controller {
 public function setup(Request $r){
  $selectedCourse=old('course',$r->user()->course);
  $courses=User::where('account_type','student')->whereNotNull('course')->where('course','<>','')
   ->distinct()->orderBy('course')->pluck('course')->push('BSIT')->prepend($selectedCourse)
   ->filter(fn($course)=>is_string($course)&&filled($course)&&$course!=='__other__')
   ->unique(fn($course)=>mb_strtoupper($course))->sort(SORT_NATURAL|SORT_FLAG_CASE);
  return view('auth.profile',compact('courses','selectedCourse'));
 }
 public function show(Request $r){
  $u=$r->user()->load('organization');
  return view('account.profile',compact('u'));
 }
 public function save(Request $r){
  $u=$r->user();
  $v=$r->validate(['name'=>'required|string|max:120','account_type'=>['required',Rule::in(['student','employee','guardian'])],'student_id'=>['nullable','required_if:account_type,student','string','max:40',Rule::unique('users')->ignore($u->id)],'course'=>'nullable|required_if:account_type,student|string|max:100','year_level'=>'nullable|required_if:account_type,student|string|max:30','office'=>'nullable|required_if:account_type,employee|string|max:120','employee_id'=>'nullable|required_if:account_type,employee|string|max:40','ward_name'=>'nullable|required_if:account_type,guardian|string|max:120','ward_student_id'=>'nullable|required_if:account_type,guardian|string|max:40','phone'=>'nullable|string|max:30','avatar'=>'nullable|image|max:2048']);
  if(($v['course']??null)==='__other__'){
   $v['course']=$r->validate(['other_course'=>'required|string|max:100|not_in:__other__'],[],['other_course'=>'course'])['other_course'];
  }
  unset($v['avatar']);
  if($r->hasFile('avatar'))$v['avatar']=$r->file('avatar')->store('avatars','public');
  $u->update([...$v,'profile_complete'=>true]);return redirect('/home')->with('success','Profile saved.');
 }
 public function settings(Request $r){
  $r->validate(['current_password'=>'nullable|required_with:password|current_password','password'=>'nullable|min:10|confirmed','two_factor'=>'nullable|boolean']);
  $u=$r->user();
  if($r->filled('password'))$u->password=$r->password;
  if($r->boolean('two_factor')&&!$u->hasVerifiedEmail())return back()->withErrors(['two_factor'=>'Verify your email before enabling sign-in codes.']);
  $u->two_factor=$r->boolean('two_factor');
  $u->preferences=['email'=>$r->boolean('email_notifications'),'in_app'=>$r->boolean('in_app_notifications')];
  $u->save();return back()->with('success','Settings saved.');
 }
}


