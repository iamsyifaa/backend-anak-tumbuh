<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = ['student_profile_id', 'award_id', 'template_id', 'file_path', 'issued_at'];

    protected $casts = ['issued_at' => 'datetime'];

    public function studentProfile()
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function award()
    {
        return $this->belongsTo(Award::class);
    }
}