<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Award;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Habit;
use App\Models\HabitConfig;
use App\Models\HabitIndicator;
use App\Models\IndicatorOption;
use App\Models\PointConfig;
use App\Models\ReportExport;
use App\Models\Rombel;
use App\Models\School;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\User;
use App\Policies\AcademicYearPolicy;
use App\Policies\AwardPolicy;
use App\Policies\BadgePolicy;
use App\Policies\CertificatePolicy;
use App\Policies\CertificateTemplatePolicy;
use App\Policies\HabitConfigPolicy;
use App\Policies\HabitPolicy;
use App\Policies\PointConfigPolicy;
use App\Policies\PrincipalPolicy;
use App\Policies\ReportExportPolicy;
use App\Policies\SchoolFeatureSettingPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\StudentAchievementPolicy;
use App\Policies\StudentProfilePolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\TeacherPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        School::class => SchoolPolicy::class,
        AcademicYear::class => AcademicYearPolicy::class,
        User::class => UserPolicy::class,

        // --- AUTH-004: Policy Otorisasi Pembiasaan ---
        Habit::class => HabitPolicy::class,
        HabitIndicator::class => HabitPolicy::class,
        IndicatorOption::class => HabitPolicy::class,
        HabitConfig::class => HabitConfigPolicy::class,

        // --- SEC-005: Policy Otorisasi Poin ---
        PointConfig::class => PointConfigPolicy::class,

        // --- SEC-006: Policy Otorisasi Submisi & Lock ---
        ActivitySubmission::class => SubmissionPolicy::class,

        // --- SEC-007 / MASTER-006: Policy Otorisasi Pencapaian & Setting Sekolah ---
        Badge::class => BadgePolicy::class,
        Award::class => AwardPolicy::class,
        StudentBadge::class => StudentAchievementPolicy::class,
        StudentAward::class => StudentAchievementPolicy::class,
        SchoolFeatureSetting::class => SchoolFeatureSettingPolicy::class,

        // --- MASTER-007: Policy Otorisasi Certificate Template ---
        CertificateTemplate::class => CertificateTemplatePolicy::class,

        // --- SEC-008 & SEC Modul Lain ---
        Certificate::class => CertificatePolicy::class,
        StudentProfile::class => StudentProfilePolicy::class,
        Rombel::class => TeacherPolicy::class,

        // --- SEC-011: Policy Otorisasi Export Report ---
        ReportExport::class => ReportExportPolicy::class,
    ];

    public function boot(): void
    {
        // 1. Register policies terlebih dahulu
        $this->registerPolicies();

        // 2. Global Bypass untuk Super Admin
        Gate::before(function ($user, $ability, $arguments = []) {
            $isSuperAdmin = $user->role === 'super_admin' || (method_exists($user, 'hasRole') && $user->hasRole('super_admin'));

            if (! $isSuperAdmin) {
                return null;
            }

            // Ability ini MEMANG bukan wewenang Super Admin sama sekali (murni tugas
            // Siswa/Wali Kelas) — jangan pernah di-bypass di sini, biar
            // permission matrix (Gate::define) yang menentukan hasilnya.
            $neverBypassForSuperAdmin = [
                'activity.submit.digital',
                'comment.create',
                'comment.reply',
            ];

            if (in_array($ability, $neverBypassForSuperAdmin, true)) {
                return null;
            }

            // Urai parameter target
            $target = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            if (is_array($target) && count($target) > 1) {
                $target = $target[1] ?? $target[0];
            }

            // Jika entitas adalah konfigurasi ber-versi (PointConfig atau HabitConfig)
            // yang SUDAH published, jangan bypass — immutability tetap berlaku
            // walaupun user-nya Super Admin.
            if ($target instanceof PointConfig || $target instanceof HabitConfig) {
                $isDraft = (isset($target->status) && strtolower((string) $target->status) === 'draft')
                    || (array_key_exists('published_at', $target->getAttributes()) && is_null($target->published_at));

                if (! $isDraft && in_array($ability, ['update', 'delete', 'publish'], true)) {
                    return null;
                }
            }

            return true;
        });

        // 3. Register permissions dari config/permissions.php
        $permissionRoles = [];
        foreach (config('permissions', []) as $role => $permissions) {
            foreach ($permissions as $permission) {
                $permissionRoles[$permission][] = $role;
            }
        }

        foreach ($permissionRoles as $permission => $roles) {
            Gate::define($permission, fn ($user) => in_array($user->role, $roles, true));
        }

        // ── SEC-010 ─────────────────────────────────────────────────────
        Gate::define('principal.dashboard.view', [PrincipalPolicy::class, 'viewDashboard']);
    }
}
