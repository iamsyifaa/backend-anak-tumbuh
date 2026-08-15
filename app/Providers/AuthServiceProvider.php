<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Award;
use App\Models\Badge;
use App\Models\Habit;
use App\Models\HabitConfig;
use App\Models\HabitIndicator;
use App\Models\IndicatorOption;
use App\Models\PointConfig;
use App\Models\School;
use App\Models\User;
use App\Policies\AcademicYearPolicy;
use App\Policies\AwardPolicy;
use App\Policies\BadgePolicy;
use App\Policies\HabitConfigPolicy;
use App\Policies\HabitPolicy;
use App\Policies\PointConfigPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\SubmissionPolicy;
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

        // --- MASTER-006: Policy Otorisasi Badge & Award ---
        Badge::class => BadgePolicy::class,
        Award::class => AwardPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Gate flat-permission (mis. Gate::allows('report.export')) diturunkan otomatis
        // dari config/permissions.php — satu sumber kebenaran, tidak dobel-maintain.
        // Scope (sekolah/rombel/diri-sendiri) TETAP dicek terpisah lewat Policy per-model,
        // Gate ini cuma menjawab "role ini punya permission ini atau tidak".
        //
        // Diratakan dulu jadi permission => [role, role, ...] SEBELUM Gate::define,
        // supaya satu permission yang dimiliki beberapa role (mis. school.manage oleh
        // super_admin & kepala_sekolah) tidak saling menimpa gara-gara Gate::define
        // dipanggil ulang per role.
        $permissionRoles = [];
        foreach (config('permissions') as $role => $permissions) {
            foreach ($permissions as $permission) {
                $permissionRoles[$permission][] = $role;
            }
        }

        foreach ($permissionRoles as $permission => $roles) {
            Gate::define($permission, fn ($user) => in_array($user->role, $roles, true));
        }
    }
}