<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
class User extends Authenticatable implements MustVerifyEmail {
 use Notifiable;
 protected $guarded=['id'];
 protected $hidden=['password','remember_token'];
 protected function casts():array{return ['password'=>'hashed','email_verified_at'=>'datetime','preferences'=>'array'];}
 public function isStaff():bool{return in_array($this->role,['admin','assistant','sfu_head','cvu_head','slu_head']);}
 public function hasScholarPortalAccess():bool{return $this->account_type==='student'&&$this->requests()->where('kind','scholarship')->whereIn('status',['unit_approved','awaiting_admin','approved'])->exists();}
 public function requests(){return $this->hasMany(ServiceRequest::class);}
 public function assignment(){return $this->hasOne(Assignment::class)->latestOfMany();}
 public function assignments(){return $this->hasMany(Assignment::class);}
 public function organization(){return $this->belongsTo(Organization::class);}
 public function canManageOrganization():bool{
  return $this->organization_id!==null
   &&(in_array($this->role,['admin','slu_head'],true)||strtolower(trim($this->position??''))==='president');
 }
}
