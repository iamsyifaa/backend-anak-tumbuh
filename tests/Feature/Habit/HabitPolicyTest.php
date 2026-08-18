<?php

namespace Tests\Feature\Habit;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HabitPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Struktur global (habits/habit_indicators/indicator_options) HANYA boleh
     * diubah Super Admin — walaupun Kepala Sekolah punya 'habit.manage' di
     * permission matrix, itu berlaku untuk HabitConfig (scoped ke sekolahnya),
     * BUKAN struktur global yang berdampak ke semua sekolah sekaligus.
     */
    public function test_only_super_admin_can_create_global_habit(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->assertTrue(Gate::forUser($admin)->allows('create', Habit::class));

        foreach (['kepala_sekolah', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertFalse(
                Gate::forUser($user)->allows('create', Habit::class),
                "Role '{$role}' seharusnya TIDAK boleh membuat struktur habit global."
            );
        }
    }

    public function test_only_super_admin_can_update_or_delete_global_habit(): void
    {
        $habit = Habit::create(['code' => 'BANGUN_PAGI', 'name' => 'Bangun Pagi', 'sort_order' => 1]);
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->assertFalse(Gate::forUser($kepsek)->allows('update', $habit));
        $this->assertFalse(Gate::forUser($kepsek)->allows('delete', $habit));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $habit));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $habit));
    }

    public function test_all_roles_can_view_global_habit_structure(): void
    {
        foreach (['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Habit::class));
        }
    }
}
