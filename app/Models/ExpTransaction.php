<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpTransaction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'amount', 'source_type', 'source_id', 'period_date'];
}