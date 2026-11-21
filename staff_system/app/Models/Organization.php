<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Organization extends Model {protected $guarded=['id'];protected function casts():array{return ['moderators'=>'array'];}public function members(){return $this->hasMany(User::class);}public function posts(){return $this->hasMany(Post::class);} }
