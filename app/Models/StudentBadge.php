<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentBadge extends Model
{
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = ['student_profile_id', 'badge_id', 'awarded_at'];

    protected $casts = ['awarded_at' => 'datetime'];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}