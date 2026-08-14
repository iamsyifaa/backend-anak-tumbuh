<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySubmission extends Model
{
    use HasFactory;

    protected $fillable = ['student_profile_id', 'activity_date', 'submitted_at', 'locked_at', 'status'];

    protected $casts = [
        'activity_date' => 'date',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'status' => 'string',
    ];

    public function studentProfile()
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->locked_at !== null;
    }
}