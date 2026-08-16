<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityComment extends Model
{
    protected $fillable = ['activity_submission_id', 'user_id', 'parent_id', 'body'];

    public function activitySubmission()
    {
        return $this->belongsTo(ActivitySubmission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ActivityComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ActivityComment::class, 'parent_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}