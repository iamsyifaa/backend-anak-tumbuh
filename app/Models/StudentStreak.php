<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStreak extends Model
{
    protected $fillable = ['user_id', 'month', 'year', 'opportunities_used', 'current_streak_days', 'last_active_date'];

    protected $casts = ['last_active_date' => 'date'];

    public function hasOpportunityLeft(): bool
    {
        return $this->opportunities_used < 7;
    }
}
