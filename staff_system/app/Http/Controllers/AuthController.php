<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,Password,Mail,Http};
use Illuminate\Support\Str;
class AuthController extends Controller {
 public function login(Request $r){
  $v=$r->validate(['login'=>'required|string|max:255','password'=>'required|string']);
  $field=filter_var($v['login'],FILTER_VALIDATE_EMAIL)?'email':'username';
  $u=User::where($field,$v['login'])->first();
  if(!$u||!Hash::check($v['password'],$u->password))return back()->withErrors(['login'=>'The account or password is incorrect.'])->onlyInput('login');
  if(!$u->isStaff())return back()->withErrors(['login'=>'Use the normal-user system to sign in with this account.']);
  if($u->two_factor){
   $code=(string)random_int(100000,999999);
   $r->session()->put('challenge',['user'=>$u->id,'hash'=>Hash::make($code),'expires'=>time()+600,'remember'=>$r->boolean('remember')]);
   Mail::raw("Your iServe sign-in code is ".$code.". It expires in 10 minutes.",fn($m)=>$m->to($u->email)->subject('iServe sign-in code'));
   return redirect('/two-factor');
  }
  Auth::login($u,$r->boolean('remember'));$r->session()->regenerate();
  \Illuminate\Support\Facades\DB::table('audit_logs')->insert(['user_id'=>$u->id,'action'=>'account.login','subject'=>$u->email,'details'=>'Staff portal login','created_at'=>now()]);
  return redirect()->intended($u->isStaff()?'/staff':($u->profile_complete?'/home':'/profile/setup'));
 }
 public function challenge(Request $r){
  $r->validate(['code'=>'required|digits:6']);$c=$r->session()->get('challenge');
  if(!$c||$c['expires']<time()||!Hash::check($r->code,$c['hash']))return back()->withErrors(['code'=>'Incorrect or expired code.']);
  $u=User::findOrFail($c['user']);Auth::login($u,$c['remember']);$r->session()->forget('challenge');$r->session()->regenerate();return redirect($u->isStaff()?'/staff':'/home');
 }
 public function register(Request $r){
  $r->merge(['email'=>strtolower(trim((string)$r->email))]);
  $v=$r->validate(['email'=>'required|email|max:255|unique:users','username'=>'required|alpha_dash|min:3|max:50|unique:users','password'=>'required|confirmed|min:10']);
  $u=User::create([...$v,'name'=>$v['username'],'role'=>'student']);Auth::login($u);$r->session()->regenerate();$u->sendEmailVerificationNotification();return redirect('/profile/setup');
 }
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/login');}
 public function forgot(Request $r){$r->validate(['email'=>'required|email']);Password::sendResetLink($r->only('email'));return back()->with('success','If this email is registered, a reset link has been sent.');}
 public function reset(Request $r){
  $r->validate(['token'=>'required','email'=>'required|email','password'=>'required|confirmed|min:10']);
  $status=Password::reset($r->only('email','password','password_confirmation','token'),function(User $u,string $p){$u->forceFill(['password'=>$p,'remember_token'=>Str::random(60)])->save();});
  return $status===Password::PASSWORD_RESET?redirect('/login')->with('success',__($status)):back()->withErrors(['email'=>__($status)]);
 }
 public function google(Request $r){
  $this->requireCondition(config('services.google.client_id'),503,'Google sign-in needs an administrator to configure OAuth credentials.');
  $state=Str::random(40);$r->session()->put('google_state',$state);
  return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>config('services.google.client_id'),'redirect_uri'=>url('/auth/google/callback'),'response_type'=>'code','scope'=>'openid email profile','state'=>$state,'prompt'=>'select_account']));
 }
 public function googleCallback(Request $r){
  $this->requireCondition($r->state&&hash_equals((string)$r->session()->pull('google_state'),(string)$r->state),403);
  $token=Http::asForm()->post('https://oauth2.googleapis.com/token',['code'=>$r->code,'client_id'=>config('services.google.client_id'),'client_secret'=>config('services.google.client_secret'),'redirect_uri'=>url('/auth/google/callback'),'grant_type'=>'authorization_code'])->throw()->json();
  $info=Http::withToken($token['access_token'])->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
  $this->requireCondition($info['email_verified']??false,403);
  $u=User::where('email',$info['email'])->first();
  if($u?->two_factor)return redirect('/login')->withErrors(['login'=>'Use your password and sign-in code for this protected account.']);
  $u??=User::create(['email'=>$info['email'],'name'=>$info['name']??'Student','username'=>'user_'.Str::lower(Str::random(12)),'password'=>Str::random(64),'role'=>'student','email_verified_at'=>now()]);
  Auth::login($u);$r->session()->regenerate();return redirect($u->isStaff()?'/staff':($u->profile_complete?'/home':'/profile/setup'));
 }
}

