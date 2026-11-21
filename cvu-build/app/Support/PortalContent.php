<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class PortalContent {
 public static function metadata(int $id):array{return json_decode(DB::table('settings')->where('key','staff.content.'.$id)->value('value')??'[]',true)??[];}
 public static function active($item,$user=null):bool{$meta=self::metadata($item->id);if(!empty($meta['ends_at'])&&\Carbon\Carbon::parse($meta['ends_at'])->isPast())return false;if(($meta['audience']??'All Students')==='All Student Leaders'&&(!$user||!$user->organization_id))return false;return true;}
 public static function destination(?string $key):?string{return ['events'=>'/portal/leadership/events','organizations'=>'/portal/leadership/organization','proposals'=>'/apply/activity','achievements'=>'/apply/achievement'][$key??'']??null;}
}
