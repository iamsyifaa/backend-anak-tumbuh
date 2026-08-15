<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasFactory;

    public const TARGET_TOTAL_POINTS = 'total_points';
    public const TARGET_TOTAL_EXP = 'total_exp';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon_path',
        'target_type',
        'target_value',
        'criteria',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'target_value' => 'integer',
        'criteria' => 'array',
    ];

    public function studentBadges(): HasMany
    {
        return $this->hasMany(StudentBadge::class);
    }
}