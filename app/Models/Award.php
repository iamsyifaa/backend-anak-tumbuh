<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Award extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'description',
        'criteria',
        'generates_certificate',
        'active',
    ];

    protected $casts = [
        'criteria' => 'array',
        'generates_certificate' => 'boolean',
        'active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function studentAwards(): HasMany
    {
        return $this->hasMany(StudentAward::class);
    }
}