<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class ProfileController extends Controller {
 public function show(Request $r){
  $u=$r->user()->load('organization');
  $requests=$u->requests()->latest()->get();
  $attendances=\App\Models\Attendance::where('user_id',$u->id)->get();
  $xp=\App\Models\Point::where('user_id',$u->id)->sum('amount');
  $scholarship=$requests->first(fn($q)=>$q->kind==='scholarship'&&$q->status==='approved');
  $rate=(int)($scholarship?->details['discount_rate']??0);
  return view('account.profile',compact('u','requests','attendances','xp','rate'));
 }
 public function save(Request $r){
  $u=$r->user();
  $v=$r->validate(['name'=>'required|string|max:120','account_type'=>['required',Rule::in(['student','employee','guardian'])],'student_id'=>['nullable','required_if:account_type,student','string','max:40',Rule::unique('users')->ignore($u->id)],'course'=>'nullable|required_if:account_type,student|string|max:100','year_level'=>'nullable|required_if:account_type,student|string|max:30','office'=>'nullable|required_if:account_type,employee|string|max:120','employee_id'=>'nullable|required_if:account_type,employee|string|max:40','ward_name'=>'nullable|required_if:account_type,guardian|string|max:120','ward_student_id'=>'nullable|required_if:account_type,guardian|string|max:40','phone'=>'nullable|string|max:30','avatar'=>'nullable|image|max:2048']);
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


