<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Event extends Model {
 private const VENUE_KEY_PREFIX='staff.event-venue.';

 protected $guarded=['id'];
 protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','metadata'=>'array'];}
 public function attendances(){return $this->hasMany(Attendance::class);}

 public static function venueChoices(Collection $eventVenues):Collection {
  $saved=DB::table('settings')->where('key','like',self::VENUE_KEY_PREFIX.'%')->pluck('value','key');
  return $eventVenues->reject(fn($venue)=>$saved->get(self::venueKey($venue))==='')
   ->merge($saved->values())->filter(fn($venue)=>filled($venue))->unique()->sort();
 }

 public static function rememberVenues(string ...$venues):void {
  sort($venues,SORT_STRING);
  foreach(array_unique($venues) as $venue){
   DB::table('settings')->insertOrIgnore(['key'=>self::venueKey($venue),'value'=>$venue]);
  }
 }

 public static function setVenueAvailability(string $venue,bool $available):void {
  DB::table('settings')->upsert([['key'=>self::venueKey($venue),'value'=>$available?$venue:'']],['key'],['value']);
 }

 private static function venueKey(string $venue):string {
  return self::VENUE_KEY_PREFIX.hash('sha256',$venue);
 }
}
