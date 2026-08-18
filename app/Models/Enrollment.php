<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enrollment = satu periode penempatan siswa (tahun ajaran + rombel).
 * Jangan pernah "update in place" untuk pindah kelas — selalu tutup
 * (status=ended, ended_at diisi) enrollment lama, lalu buat baris baru.
 * Lihat StudentEnrollmentService (akan dibuat saat logic pindah kelas
 * diperlukan) untuk memastikan aturan ini konsisten.
 */
class Enrollment extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'rombel_id',
        'status',
        'started_at',
        'ended_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
