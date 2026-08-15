<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolFeatureSetting extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'ranking_class_enabled', 'ranking_cohort_enabled'];

    protected $casts = [
        'ranking_class_enabled' => 'boolean',
        'ranking_cohort_enabled' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}