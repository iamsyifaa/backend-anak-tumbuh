<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndicatorOption extends Model
{
    use HasFactory;

    protected $fillable = ['indicator_id', 'label', 'value', 'point_value', 'exp_value', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function indicator()
    {
        return $this->belongsTo(HabitIndicator::class, 'indicator_id');
    }
}
