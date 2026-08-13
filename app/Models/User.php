<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_KEPALA_SEKOLAH = 'kepala_sekolah';
    public const ROLE_WALI_KELAS = 'wali_kelas';
    public const ROLE_SISWA = 'siswa';

    protected $fillable = [
        'school_id',
        'name',
        'username',
        'email',
        'password',
        'role',
        'status',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => 'string',
        'role' => 'string',
        'must_change_password' => 'boolean',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ── Status Helpers ──

    public function isActive(): bool
    {
        return $this->is_active || $this->status === 'active';
    }

    // ── Relations ──────────────────────────────────────────────

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Profile aktif sesuai role user. Mengembalikan null untuk
     * super_admin karena role tersebut tidak punya tabel profile.
     */
    public function getProfileAttribute(): ?object
    {
        return match ($this->role) {
            self::ROLE_KEPALA_SEKOLAH, self::ROLE_WALI_KELAS => $this->teacherProfile,
            self::ROLE_SISWA => $this->studentProfile,
            default => null,
        };
    }

    // ── Role helpers (murni pengecekan, bukan authorization logic) ──

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isKepalaSekolah(): bool
    {
        return $this->role === self::ROLE_KEPALA_SEKOLAH;
    }

    public function isWaliKelas(): bool
    {
        return $this->role === self::ROLE_WALI_KELAS;
    }

    public function isSiswa(): bool
    {
        return $this->role === self::ROLE_SISWA;
    }
}