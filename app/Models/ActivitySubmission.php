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

    // FIX (review MASTER-005, Anggota B): relasi ke submission_answers
    // (Anggota C, BE-005/006/007) supaya PointCalculationService bisa
    // menghitung poin dari jawaban tiap submission.
    public function answers()
    {
        return $this->hasMany(SubmissionAnswer::class);
    }
}