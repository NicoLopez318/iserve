<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Organization extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['moderators'=>'array'];}
 public function members(){return $this->hasMany(User::class);}
 public function posts(){return $this->hasMany(Post::class);}

 private static function hiddenFromStudentPortalIds():array
 {
  $prefix='staff.organization.';
  return DB::table('settings')->where('key','like',$prefix.'%')->pluck('value','key')
   ->filter(fn($value)=>(json_decode($value,true)['portal_visible']??true)===false)
   ->keys()->map(fn($key)=>(int)substr($key,strlen($prefix)))->all();
 }

 public function scopeVisibleInStudentPortal(Builder $query):Builder
 {
  return $query->whereNotIn('id',self::hiddenFromStudentPortalIds());
 }

 public static function visibleInStudentPortalRule():Exists
 {
  return Rule::exists('organizations','id')->whereNotIn('id',self::hiddenFromStudentPortalIds());
 }
}
