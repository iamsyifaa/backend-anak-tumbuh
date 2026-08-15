<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Award;
use App\Models\Badge;
use App\Models\Certificate;
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

        // --- SEC-008 & SEC Modul Lain ---
        Certificate::class => CertificatePolicy::class,
        StudentProfile::class => StudentProfilePolicy::class,
        Rombel::class => TeacherPolicy::class,
        ReportExport::class => ReportExportPolicy::class,
    ];

    public function boot(): void
    {
        // 1. Register policies terlebih dahulu
        $this->registerPolicies();

        // 2. Global Bypass untuk Super Admin (Dijalankan sebelum Policy/Gate lain)
        Gate::before(function ($user, $ability) {
            if ($user->role === 'super_admin' || (method_exists($user, 'hasRole') && $user->hasRole('super_admin'))) {
                return true;
            }
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

        // ── SEC-010 ──────────────────────────────────────────────────────
        // Named ability untuk Policy yang tidak attached ke satu model
        Gate::define('principal.dashboard.view', [PrincipalPolicy::class, 'viewDashboard']);
    }
}