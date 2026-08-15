<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionAnswer extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['activity_submission_id', 'indicator_id', 'indicator_option_id'];

    public function activitySubmission()
    {
        return $this->belongsTo(ActivitySubmission::class);
    }

    public function indicator()
    {
        return $this->belongsTo(HabitIndicator::class, 'indicator_id');
    }

    public function option()
    {
        return $this->belongsTo(IndicatorOption::class, 'indicator_option_id');
    }
}