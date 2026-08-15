<?php

namespace Tests\Feature;

use App\Models\ActivitySubmission;
use App\Models\PointTransaction;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\PointCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): StudentProfile
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);

        return StudentProfile::factory()->create(['user_id' => $user->id]);
    }

    // ── Fix bug fatal: model yang benar, tidak crash ───────────

    public function test_service_uses_correct_activity_submission_model(): void
    {
        $profile = $this->makeStudent();
        $submission = ActivitySubmission::create([
            'student_profile_id' => $profile->id,
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $service = app(PointCalculationService::class);

        // Belum ada relasi/fitur 'answers' (dependency BE-005/006/007
        // Anggota C belum dikerjakan) — service harus menolak dengan
        // jelas, BUKAN fatal "Class not found" seperti versi sebelumnya.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('answers');

        $service->calculateAndRecord($submission);
    }

    // ── Otorisasi endpoint GET /points ─────────────────────────

    public function test_siswa_can_view_own_points(): void
    {
        $profile = $this->makeStudent();

        $response = $this->actingAs($profile->user, 'sanctum')
            ->getJson("/api/students/{$profile->id}/points");

        $response->assertOk();
    }

    public function test_siswa_cannot_view_other_students_points(): void
    {
        $ownProfile = $this->makeStudent();
        $otherProfile = $this->makeStudent();

        $response = $this->actingAs($ownProfile->user, 'sanctum')
            ->getJson("/api/students/{$otherProfile->id}/points");

        $response->assertStatus(403);
    }

    public function test_wali_kelas_can_view_any_student_points(): void
    {
        $profile = $this->makeStudent();
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);

        $response = $this->actingAs($wali, 'sanctum')
            ->getJson("/api/students/{$profile->id}/points");

        $response->assertOk();
    }

    // ── Total poin dihitung benar dari struktur asli (user_id) ──

    public function test_points_total_sums_transactions_correctly(): void
    {
        $profile = $this->makeStudent();

        PointTransaction::create([
            'user_id' => $profile->user_id,
            'amount' => 10,
            'source_type' => 'submission_answer',
            'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);
        PointTransaction::create([
            'user_id' => $profile->user_id,
            'amount' => 15,
            'source_type' => 'submission_answer',
            'source_id' => 2,
            'period_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($profile->user, 'sanctum')
            ->getJson("/api/students/{$profile->id}/points");

        $response->assertOk()->assertJsonPath('data.total_points', 25);
    }
}