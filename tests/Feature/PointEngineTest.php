<?php

namespace Tests\Feature;

use App\Models\ActivitySubmission;
use App\Models\Habit;
use App\Models\PointTransaction;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\SubmissionAnswer;
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
    // Sekarang relasi answers() sudah ada (Anggota C, BE-005/006/007),
    // jadi errornya bukan lagi "relasi tidak ada" tapi "belum ada
    // jawaban" — karena submission di test ini memang tidak diisi
    // jawaban sama sekali. Ini pengecekan yang benar (bukan fatal crash).

    public function test_service_rejects_submission_without_answers(): void
    {
        $profile = $this->makeStudent();
        $submission = ActivitySubmission::create([
            'student_profile_id' => $profile->id,
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $service = app(PointCalculationService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum punya jawaban');

        $service->calculateAndRecord($submission);
    }

    // ── Integrasi penuh: submission dengan jawaban benar-benar hitung poin ──

    public function test_calculate_and_record_awards_correct_points_from_answers(): void
    {
        $profile = $this->makeStudent();
        $habit = Habit::create(['code' => 'BANGUN_PAGI', 'name' => 'Bangun Pagi']);
        $indicator = $habit->indicators()->create(['code' => 'JAM_BANGUN', 'label' => 'Jam Bangun', 'is_required' => true]);
        $option = $indicator->options()->create(['label' => 'Sebelum 6', 'value' => 'before_6', 'point_value' => 10]);

        $submission = ActivitySubmission::create([
            'student_profile_id' => $profile->id,
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $indicator->id,
            'indicator_option_id' => $option->id,
        ]);

        $service = app(PointCalculationService::class);
        $total = $service->calculateAndRecord($submission);

        $this->assertSame(10, $total);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $profile->user_id,
            'amount' => 10,
            'source_type' => 'submission_answer',
        ]);
    }

    public function test_calculate_and_record_is_idempotent_per_answer(): void
    {
        $profile = $this->makeStudent();
        $habit = Habit::create(['code' => 'X', 'name' => 'X']);
        $indicator = $habit->indicators()->create(['code' => 'IND', 'label' => 'Ind', 'is_required' => true]);
        $option = $indicator->options()->create(['label' => 'A', 'value' => 'a', 'point_value' => 5]);

        $submission = ActivitySubmission::create([
            'student_profile_id' => $profile->id,
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        SubmissionAnswer::create([
            'activity_submission_id' => $submission->id,
            'indicator_id' => $indicator->id,
            'indicator_option_id' => $option->id,
        ]);

        $service = app(PointCalculationService::class);
        $firstRun = $service->calculateAndRecord($submission);
        $secondRun = $service->calculateAndRecord($submission->fresh());

        $this->assertSame(5, $firstRun);
        $this->assertSame(0, $secondRun); // sudah pernah dicatat, tidak dobel
        $this->assertDatabaseCount('point_transactions', 1);
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