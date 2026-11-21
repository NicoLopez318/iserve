<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\{AuthController,ProfileController,PortalController,RequestController,StaffController};
Route::get('/',[App\Http\Controllers\PublicController::class,'page']);
Route::get('/services',fn(Request $r)=>app(App\Http\Controllers\PublicController::class)->page($r,'services'));
Route::get('/units',fn(Request $r)=>app(App\Http\Controllers\PublicController::class)->page($r,'units'));
Route::get('/units/{unit}',fn(Request $r,string $unit)=>app(App\Http\Controllers\PublicController::class)->page($r,'units',$unit));
Route::get('/announcements',fn(Request $r)=>app(App\Http\Controllers\PublicController::class)->page($r,'announcements'));
Route::get('/about',fn(Request $r)=>app(App\Http\Controllers\PublicController::class)->page($r,'about'));
Route::middleware('guest')->group(function(){
 Route::view('/login','auth.login')->name('login');
 Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');
 Route::view('/register','auth.register');
 Route::post('/register',[AuthController::class,'register'])->middleware('throttle:5,1');
 Route::view('/forgot-password','auth.forgot')->name('password.request');
 Route::post('/forgot-password',[AuthController::class,'forgot'])->middleware('throttle:3,1');
 Route::get('/reset-password/{token}',fn(Request $r,string $token)=>view('auth.reset',['token'=>$token,'email'=>$r->email]))->name('password.reset');
 Route::post('/reset-password',[AuthController::class,'reset'])->middleware('throttle:5,1');
 Route::view('/two-factor','auth.challenge');
 Route::post('/two-factor',[AuthController::class,'challenge'])->middleware('throttle:5,1');
 Route::get('/auth/google',[AuthController::class,'google']);
 Route::get('/auth/google/callback',[AuthController::class,'googleCallback']);
});
Route::middleware('auth')->group(function(){
 Route::get('/avatar',function(Request $r){$path=$r->user()->avatar;abort_unless($path&&Illuminate\Support\Facades\Storage::disk('public')->exists($path),404);return Illuminate\Support\Facades\Storage::disk('public')->response($path);});
 Route::post('/logout',[AuthController::class,'logout']);
 Route::view('/profile/setup','auth.profile');
 Route::post('/profile',[ProfileController::class,'save']);
 Route::post('/settings',[ProfileController::class,'settings']);
 Route::view('/email/verify','auth.verify')->name('verification.notice');
 Route::get('/email/verify/{id}/{hash}',function(EmailVerificationRequest $r){$r->fulfill();return redirect('/home')->with('success','Email verified.');})->middleware('signed')->name('verification.verify');
 Route::post('/email/verification-notification',function(Request $r){$r->user()->sendEmailVerificationNotification();return back()->with('success','Verification email sent.');})->middleware('throttle:3,1')->name('verification.send');
 Route::get('/home',[PortalController::class,'home'])->name('home');
 Route::view('/transactions','transactions.select');
 Route::view('/community-link','transactions.community');
 Route::get('/apply/{kind}',[RequestController::class,'form']);
 Route::post('/proposal-draft',[RequestController::class,'draft'])->middleware(['verified','throttle:10,1']);
 Route::post('/apply/{kind}',[RequestController::class,'store'])->middleware(['verified','throttle:10,1']);
 Route::get('/requests/{serviceRequest}',[RequestController::class,'show']);
 Route::post('/requests/{serviceRequest}/cancel',[RequestController::class,'cancel']);
 Route::post('/requests/{serviceRequest}/revise',[RequestController::class,'revise'])->middleware('verified');
 Route::get('/requests/{serviceRequest}/files/{key}',[RequestController::class,'download']);
 Route::get('/requests/{serviceRequest}/files/{key}/preview',[RequestController::class,'imagePreview'])
  ->middleware('staff')->name('staff.requests.image-preview');
 Route::get('/portal/{portal}/{page?}',[PortalController::class,'page']);
 Route::post('/community/clock',[PortalController::class,'clock'])->middleware('verified');
 Route::post('/community/journal',[PortalController::class,'journal'])->middleware('verified');
 Route::post('/attendance/{attendance}/revise',[PortalController::class,'reviseAttendance'])->middleware('verified');
 Route::post('/events/{event}',[PortalController::class,'event'])->middleware('verified');
 Route::post('/posts',[PortalController::class,'post'])->middleware(['verified','throttle:10,1']);
 Route::post('/organization/moderators',[PortalController::class,'moderators'])->middleware('verified');
 Route::post('/posts/{post}/comments',[PortalController::class,'comment'])->middleware(['verified','throttle:20,1']);
 Route::get('/notifications',[App\Http\Controllers\StaffPortalController::class,'notifications']);
 Route::middleware('staff')->prefix('staff')->group(function(){
  Route::post('/announcements',[App\Http\Controllers\PublicController::class,'announcement']);
  Route::get('/reports',[App\Http\Controllers\ReportController::class,'index']);
  Route::post('/reports',[App\Http\Controllers\ReportController::class,'store']);
  Route::post('/reports/{report}',[App\Http\Controllers\ReportController::class,'review']);
  Route::post('/reports/{report}/revise',[App\Http\Controllers\ReportController::class,'revise']);
  Route::post('/settings',[App\Http\Controllers\ReportController::class,'settings']);
  Route::get('/',[StaffController::class,'index']);Route::get('/export',[StaffController::class,'export']);
  Route::post('/requests/{serviceRequest}',[StaffController::class,'review']);
  Route::post('/assignments',[StaffController::class,'assignment']);
  Route::post('/assignments/{assignment}/complete',[StaffController::class,'complete']);
  Route::post('/attendance/{attendance}',[StaffController::class,'attendance']);
  Route::get('/attendance/{attendance}/evidence/{side}',[StaffController::class,'evidence']);
  Route::post('/journals/{journal}',[StaffController::class,'journal']);
  Route::post('/events',[StaffController::class,'event']);
  Route::post('/events/{event}/cancel',[StaffController::class,'cancelEvent']);
  Route::post('/users',[StaffController::class,'user']);
 });
});


require __DIR__.'/workspace.php';

Route::get('/content-image/{id}',[App\Http\Controllers\PublicController::class,'contentImage'])->whereNumber('id');

Route::post('/events/{event}/completion',[App\Http\Controllers\EventCompletionController::class,'store'])->middleware(['auth','verified','throttle:10,1']);
