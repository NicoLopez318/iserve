<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
class ServiceRequest extends Model {
 public const MEMBERSHIP_STATUSES=[
  'pending'=>'New','in_review'=>'For Review','needs_revision'=>'Incomplete','unit_approved'=>'Unit Approved',
  'awaiting_admin'=>'Under Admin Review','approved'=>'Approved','rejected'=>'Declined','cancelled'=>'Cancelled',
 ];
 public const COMPLAINT_STATUSES=[
  'pending'=>'For Review','in_review'=>'Under Unit Review','needs_revision'=>'For Compliance',
  'unit_approved'=>'Unit Approved','awaiting_admin'=>'Under Admin Review','approved'=>'Resolved',
  'rejected'=>'Declined','cancelled'=>'Cancelled',
 ];
 public const LOST_FOUND_TYPES=['lost'=>'Lost Report','found'=>'Found Report'];
 public const LOST_FOUND_STATUSES=[
  'pending'=>'New','in_review'=>'For Review','needs_revision'=>'For Compliance',
  'unit_approved'=>'Unit Approved','awaiting_admin'=>'Under Admin Review','approved'=>'All Approved','claim_pending'=>'Claim Pending',
  'released'=>'Released','rejected'=>'Declined','cancelled'=>'Cancelled',
 ];
 public const PROPOSAL_CHECKLIST=[
  'Pre-Activity'=>['pre_intent','pre_matrix','pre_evaluation','pre_resolution','pre_budget'],
  'Post-Activity'=>['post_documentation','post_narrative','post_evaluation','post_financial','post_attendance'],
 ];
 protected $guarded=['id'];
 protected function casts():array{return ['details'=>'array','files'=>'array','reviewed_at'=>'datetime'];}
 public function user(){return $this->belongsTo(User::class);}
 public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');}
 public function scopeAwaitingRelease(Builder $query):Builder{
  return $query->where('status','approved')
   ->where(fn($items)=>$items->whereNull('details->released')->orWhere('details->released',false));
 }
 public function lostFoundStatus():string{
  return match(true){
   !empty($this->details['released'])=>'released',
   $this->status==='approved'=>'claim_pending',
   default=>$this->status,
  };
 }
 public function complaintType():?string {
  $customType=$this->details['other_complaint']??null;
  return filled($customType)?$customType:($this->details['complaint_type']??null);
 }
 public function canEditComplaintContact():bool {
  return $this->kind==='complaint'&&in_array($this->status,['pending','in_review','needs_revision'],true);
 }
 public function canMarkIdReprinted():bool {
  return $this->kind==='id-replacement'&&$this->status==='approved'
   &&empty($this->details['reprinted_at'])&&empty($this->details['released']);
 }
 public function attachmentPath(string $key):?string{
  $file=$this->files[$key]??null;
  $path=is_array($file)?($file['path']??null):$file;
  return is_string($path)&&trim($path)!==''?$path:null;
 }
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
