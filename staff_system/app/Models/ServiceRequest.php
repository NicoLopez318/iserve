<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ServiceRequest extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['details'=>'array','files'=>'array','reviewed_at'=>'datetime'];}
 public function user(){return $this->belongsTo(User::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
 public static function kinds():array{return ['scholarship'=>'Scholarship Application','leadership'=>'Leadership Application','complaint'=>'Complaints','lost'=>'Lost Item','found'=>'Found Item','readmission'=>'Readmission','id-replacement'=>'ID Replacement','clearance'=>'Clearance Appointment','seminar'=>'Seminars','activity'=>'Organization Activity Proposal','achievement'=>'Submit Achievement'];}
 public function office():string{return in_array($this->kind,['scholarship'])?'sfu_head':(in_array($this->kind,['leadership','activity','achievement'])?'slu_head':'cvu_head');}
 public function reviewActions(User $actor):array{
  if($actor->role==='admin')return $this->status==='awaiting_admin'?['approved'=>'Final approval','needs_revision'=>'Return for revision','rejected'=>'Reject']:[];
  if($actor->role==='assistant')return match($this->status){
   'pending','in_review'=>['needs_revision'=>'Return for revision'],
   'unit_approved'=>['awaiting_admin'=>'Forward to admin','needs_revision'=>'Return for revision'],
   default=>[],
  };
  if($actor->role!==$this->office())return [];
  return match($this->status){
   'pending'=>['in_review'=>'Start unit review','unit_approved'=>'Approve at unit level','needs_revision'=>'Return for revision','rejected'=>'Reject'],
   'in_review'=>['unit_approved'=>'Approve at unit level','needs_revision'=>'Return for revision','rejected'=>'Reject'],
   default=>[],
  };
 }
}
