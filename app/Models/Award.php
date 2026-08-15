<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Award extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'generates_certificate', 'active'];

    protected $casts = [
        'generates_certificate' => 'boolean',
        'active' => 'boolean',
    ];

    public function studentAwards(): HasMany
    {
        return $this->hasMany(StudentAward::class);
    }
}
