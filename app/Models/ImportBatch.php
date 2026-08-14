<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'token',
        'uploaded_by',
        'academic_year_id',
        'original_filename',
        'rows_payload',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'status',
        'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'rows_payload' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isCommittable(): bool
    {
        return $this->status === self::STATUS_PREVIEWED && $this->valid_rows > 0;
    }
}
