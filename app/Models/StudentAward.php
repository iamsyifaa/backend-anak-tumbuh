<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAward extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'student_profile_id',
        'award_id',
        'given_by',
        'note',
        'given_at',
        'awarded_at',
        'certificate_id',
    ];

    protected $casts = [
        'given_at' => 'datetime',
        'awarded_at' => 'datetime',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
