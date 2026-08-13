<?php

namespace App\Models;

<<<<<<< Updated upstream
=======
use Illuminate\Contracts\Auth\MustVerifyEmail;
>>>>>>> Stashed changes
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
<<<<<<< Updated upstream

    protected $fillable = [
        'school_id',
=======

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_KEPALA_SEKOLAH = 'kepala_sekolah';
    public const ROLE_WALI_KELAS = 'wali_kelas';
    public const ROLE_SISWA = 'siswa';

    protected $fillable = [
        'name',
>>>>>>> Stashed changes
        'username',
        'email',
        'password',
        'role',
<<<<<<< Updated upstream
        'status',
        'must_change_password',
    ];

    // password & token tidak boleh pernah ikut ter-serialize ke response (no secret leakage).
=======
        'is_active',
    ];

>>>>>>> Stashed changes
    protected $hidden = [
        'password',
        'remember_token',
    ];

<<<<<<< Updated upstream
    protected $casts = [
        'status' => 'string',
        'role' => 'string',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isKepalaSekolah(): bool
    {
        return $this->role === 'kepala_sekolah';
    }

    public function isWaliKelas(): bool
    {
        return $this->role === 'wali_kelas';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function school()
    {
        return $this->belongsTo(School::class);
=======
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
>>>>>>> Stashed changes
    }

    // ── Relations ──────────────────────────────────────────────

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
