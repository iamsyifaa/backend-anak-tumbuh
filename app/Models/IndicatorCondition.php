<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'indicator_id',
        'parent_indicator_id',
        'required_option_value',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(HabitIndicator::class, 'indicator_id');
    }

    public function parentIndicator(): BelongsTo
    {
        return $this->belongsTo(HabitIndicator::class, 'parent_indicator_id');
    }
}