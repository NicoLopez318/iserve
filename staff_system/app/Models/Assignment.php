<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assignment extends Model {
 protected $guarded=['id'];
 public function user(){return $this->belongsTo(User::class);}
 public function attendances(){return $this->hasMany(Attendance::class);}
 public function journals(){return $this->hasMany(Journal::class);}
 public function feedbackEntries(){return $this->hasMany(AssignmentFeedbackEntry::class)->latest('id');}
 public function approvedMinutes():int{return (int)$this->attendances()->where('status','approved')->sum('minutes');}
}
