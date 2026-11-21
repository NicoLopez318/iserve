<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CvuFlowController as Cvu;
Route::middleware('auth')->group(function(){
 foreach(['readmission','clearance','seminar'] as $kind)Route::get('/apply/'.$kind,fn(\Illuminate\Http\Request $r)=>app(Cvu::class)->form($r,$kind));
 Route::post('/cvu/submit/{kind}',[Cvu::class,'submit'])->middleware('throttle:10,1')->whereIn('kind',['readmission','clearance','seminar']);
 Route::get('/cvu/submitted/{serviceRequest}',[Cvu::class,'submitted']);
 Route::get('/community-link',[Cvu::class,'community']);
 Route::post('/community/confirm',[Cvu::class,'confirmStudent'])->middleware('throttle:5,1');
 Route::get('/cvu/head/{path?}',function(\Illuminate\Http\Request $r,string $path=''){abort_unless($r->user()->isStaff(),403);$section=explode('/',$path)[0];$page=match($section){'seminar'=>'seminars','readmission','clearance','community'=>$section,default=>'dashboard'};return redirect()->away(rtrim(env('STAFF_PORTAL_URL','https://goldenrod-turkey-247730.hostingersite.com'),'/').'/workspace/'.$page);})->where('path','.*');
});
