<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelThreshold extends Model
{
    use HasFactory;

    protected $fillable = ['level', 'required_exp'];
}