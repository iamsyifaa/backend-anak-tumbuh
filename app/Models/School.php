<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'status'];

    protected $casts = [
        'status' => 'string',
    ];

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function activeAcademicYear()
    {
        return $this->hasOne(AcademicYear::class)->where('status', 'active');
    }

    public function habitConfigs()
    {
        return $this->hasMany(HabitConfig::class);
    }

    public function pointConfigs()
    {
        return $this->hasMany(PointConfig::class);
    }

    public function awards()
    {
        return $this->hasMany(Award::class);
    }

    public function featureSetting()
    {
        return $this->hasOne(SchoolFeatureSetting::class);
    }

    public function educationLevels()
{
    return $this->hasMany(EducationLevel::class);
}
}
