<?php
namespace App\Support;
use App\Models\User;
use Illuminate\Support\Str;
class StaffAlerts {
 public static function role(string $role,string $title,string $url,string $category='submissions'):void{
  foreach(User::where('role',$role)->get() as $u){$preferences=$u->preferences??[];if(($preferences['in_app']??true)&&($preferences['alerts'][$category]??true))$u->notifications()->create(['id'=>Str::uuid(),'type'=>'staff-workflow','data'=>json_encode(['title'=>$title,'url'=>rtrim(env('STAFF_PORTAL_URL','http://127.0.0.1:8002'),'/').$url,'message'=>$title])]);}
 }
}
