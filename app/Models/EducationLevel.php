<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationLevel extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'name', 'order', 'status'];

    protected $casts = [
        'order' => 'integer',
        'status' => 'string',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function rombels()
    {
        return $this->hasMany(Rombel::class);
    }
}