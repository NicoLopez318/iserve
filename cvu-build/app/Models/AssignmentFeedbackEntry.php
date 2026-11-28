<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentFeedbackEntry extends Model
{
    protected $fillable = ['author_id', 'message'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
