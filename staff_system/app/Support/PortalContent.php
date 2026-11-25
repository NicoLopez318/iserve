<?php
namespace App\Support;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class PortalContent {
 public const TYPES=['Announcement Banner','Featured Event','Organization Feature','Notification','Helping Content'];
 public const LOCATIONS=['Student Dashboard','Event & Activities','Achievement Form'];

 public static function metadata(int $id):array{return json_decode(DB::table('settings')->where('key','staff.content.'.$id)->value('value')??'[]',true)??[];}
 public static function library(Collection $items):Collection
 {
  $metadata=DB::table('settings')->whereIn('key',$items->map(fn($item)=>'staff.content.'.$item->id)->all())
   ->pluck('value','key')->map(fn($value)=>json_decode($value,true)??[]);
  $now=now();

  return $items->map(function($item)use($metadata,$now){
   $meta=$metadata->get('staff.content.'.$item->id,[]);
   $start=filled($item->published_at)?rescue(fn()=>Carbon::parse($item->published_at),report:false):null;
   $end=filled($meta['ends_at']??null)?rescue(fn()=>Carbon::parse($meta['ends_at']),report:false):null;
   $status=match(true){
    $start===null=>'draft',
    $start->gt($now)=>'scheduled',
    $end?->lt($now)=>'expired',
    default=>'published',
   };
   $type=$meta['content_type']??'Announcement Banner';
   $location=$meta['portal_location']??'Student Dashboard';
   $image=!empty($meta['image']['path'])?url('/content-image/'.$item->id):null;
   return compact('item','meta','start','end','status','type','location','image');
  });
 }
 public static function active($item,$user=null):bool{$meta=self::metadata($item->id);if(!empty($meta['ends_at'])&&\Carbon\Carbon::parse($meta['ends_at'])->isPast())return false;if(($meta['audience']??'All Students')==='All Student Leaders'&&(!$user||!$user->organization_id))return false;return true;}
 public static function destination(?string $key):?string{return ['events'=>'/portal/leadership/events','organizations'=>'/portal/leadership/organization','proposals'=>'/apply/activity','achievements'=>'/apply/achievement'][$key??'']??null;}
}
