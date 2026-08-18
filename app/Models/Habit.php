<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function indicators()
    {
        return $this->hasMany(HabitIndicator::class);
    }
}
