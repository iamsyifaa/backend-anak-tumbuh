<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rombel extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'academic_year_id', 'name', 'homeroom_teacher_id', 'status'];

    protected $casts = ['status' => 'string'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function assignments()
    {
        return $this->hasMany(TeacherRombelAssignment::class);
    }
}
