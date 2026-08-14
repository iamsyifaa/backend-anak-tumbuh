<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    const UPDATED_AT = null; // insert-only, tidak ada kolom updated_at

    protected $fillable = ['user_id', 'amount', 'source_type', 'source_id', 'period_date'];
}