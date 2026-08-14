<?php

namespace Tests\Feature\Submission;

use App\Models\ActivitySubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ ASUMSI (perlu dikonfirmasi ke Anggota B kalau ada yang gagal karena beda):
 * - StudentProfile::factory() tersedia dengan default 'status' => 'active'.
 * - Relasi $user->studentProfile (hasOne) sudah ada di model User.
 * Kalau salah satu asumsi ini meleset, sesuaikan helper createSiswaWithProfile()
 * di bawah — jangan ubah file User.php/StudentProfile.php dari sini.
 */
class SubmissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createSiswaWithProfile(): User
    {
        $user = User::factory()->create(['role' => 'siswa', 'status' => 'active']);
        StudentProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        return $user->fresh();
    }

    public function test_siswa_can_create_submission_for_today(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $response = $this->actingAs($siswa, 'sanctum')->postJson('/api/submissions', [
            'activity_date' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    public function test_non_siswa_cannot_create_submission(): void
    {
        foreach (['super_admin', 'kepala_sekolah', 'wali_kelas'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')->postJson('/api/submissions', [
                'activity_date' => now()->toDateString(),
            ])->assertStatus(403);
        }
    }

    public function test_backfill_for_yesterday_is_rejected(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $response = $this->actingAs($siswa, 'sanctum')->postJson('/api/submissions', [
            'activity_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('susulan', $response->json('message'));
    }

    public function test_future_date_is_rejected(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $this->actingAs($siswa, 'sanctum')->postJson('/api/submissions', [
            'activity_date' => now()->addDay()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_duplicate_submission_same_day_is_rejected(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $this->actingAs($siswa, 'sanctum')->postJson('/api/submissions', [
            'activity_date' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAs($siswa, 'sanctum')->postJson('/api/submissions', [
            'activity_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('sudah mengisi', $response->json('message'));
    }

    public function test_siswa_cannot_view_another_students_submission(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $submission = ActivitySubmission::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/submissions/{$submission->id}")
            ->assertStatus(403);
    }

    public function test_siswa_can_view_own_submission(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $submission = ActivitySubmission::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);

        $this->actingAs($siswa, 'sanctum')
            ->getJson("/api/submissions/{$submission->id}")
            ->assertOk();
    }

    public function test_locked_submission_cannot_be_edited_again(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $submission = ActivitySubmission::factory()->locked()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);

        $this->actingAs($siswa, 'sanctum')
            ->patchJson("/api/submissions/{$submission->id}/lock")
            ->assertStatus(403);
    }

    public function test_siswa_cannot_lock_another_students_submission(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $submission = ActivitySubmission::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
            'status' => 'draft',
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->patchJson("/api/submissions/{$submission->id}/lock")
            ->assertStatus(403);
    }

    public function test_owner_can_lock_own_draft_submission(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $submission = ActivitySubmission::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($siswa, 'sanctum')
            ->patchJson("/api/submissions/{$submission->id}/lock");

        $response->assertOk()->assertJsonPath('data.status', 'locked');
    }
}