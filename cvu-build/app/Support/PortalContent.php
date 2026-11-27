<?php
namespace App\Support;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class PortalContent {
 public static function metadata(int $id):array{return json_decode(DB::table('settings')->where('key','staff.content.'.$id)->value('value')??'[]',true)??[];}
 public static function active($item,$user=null,?array $meta=null):bool{$meta??=self::metadata($item->id);if(!empty($meta['ends_at'])&&Carbon::parse($meta['ends_at'])->isPast())return false;if(($meta['audience']??'All Students')==='All Student Leaders'&&(!$user||!$user->organization_id))return false;return true;}
 public static function destination(?string $key):?string{return ['events'=>'/portal/leadership/events','organizations'=>'/portal/leadership/organization','proposals'=>'/apply/activity','achievements'=>'/apply/achievement'][$key??'']??null;}

 public static function leadershipNotifications(User $user):Collection
 {
  $items=DB::table('announcements')->where('unit','leadership')->whereDate('published_at','<=',today())
   ->orderByDesc('id')->get();
  $metadata=DB::table('settings')->whereIn('key',$items->map(fn($item)=>'staff.content.'.$item->id))
   ->pluck('value','key');
  return $items->map(function($item)use($metadata){
   $item->metadata=json_decode($metadata['staff.content.'.$item->id]??'[]',true)??[];
   $item->publishedAt=Carbon::parse($item->published_at);
   return $item;
  })->filter(fn($item)=>$item->publishedAt->lte(now())&&!empty($item->metadata['portal_location'])
   &&in_array($item->metadata['content_type']??'Announcement Banner',['Announcement Banner','Notification'],true)
   &&self::active($item,$user,$item->metadata))->sortByDesc('publishedAt');
 }

 public static function unreadLeadershipNotifications(User $user):int
 {
  $seen=DB::table('settings')->where('key',self::leadershipNotificationKey($user))->value('value');
  return self::leadershipNotifications($user)->filter(fn($item)=>$seen===null||$item->publishedAt->gt($seen))->count();
 }

 public static function markLeadershipNotificationsSeen(User $user,Collection $items):void
 {
  if($items->isEmpty())return;
  $latest=$items->first()->publishedAt->toDateTimeString();
  $key=self::leadershipNotificationKey($user);
  DB::table('settings')->insertOrIgnore(['key'=>$key,'value'=>$latest]);
  DB::table('settings')->where('key',$key)->where('value','<',$latest)->update(['value'=>$latest]);
 }

 private static function leadershipNotificationKey(User $user):string
 {
  return 'student.leadership-notifications.'.$user->id;
 }
}
