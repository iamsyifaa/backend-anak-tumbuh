<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HabitIndicator extends Model
{
    use HasFactory;

    protected $fillable = ['habit_id', 'code', 'label', 'is_required', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean', 'is_required' => 'boolean'];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }

    public function options()
    {
        return $this->hasMany(IndicatorOption::class, 'indicator_id');
    }

    public function conditions()
    {
        return $this->hasMany(IndicatorCondition::class, 'indicator_id');
    }
}