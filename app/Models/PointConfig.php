<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointConfig extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'version', 'effective_date', 'initiative_bonus_points', 'status', 'published_at', 'published_by'];

    protected $casts = [
        'effective_date' => 'date',
        'published_at' => 'datetime',
        'status' => 'string',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}