<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'type', // <-- Ditambahkan ke fillable
        'scope_type',
        'scope_id',
        'file_path',
        'format',
        'expires_at',
    ];

    protected $casts = ['expires_at' => 'datetime'];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
