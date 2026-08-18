<?php

namespace Tests\Feature\Security;

use App\Models\ActivitySubmission;
use App\Models\Certificate;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-008 — "Critical privacy boundary". Test suite ini KONSOLIDASI semua
 * skenario IDOR lintas resource siswa dalam satu tempat (submission, badge,
 * award, certificate, profile) — supaya kalau ada resource BARU siswa
 * ditambahkan tim lain nanti, tinggal tambah 1 method test di sini mengikuti
 * pola yang sama, bukan bikin file security test terpisah-pisah lagi.
 *
 * Pola serangan yang diuji SETIAP resource: siswa A mencoba akses record
 * milik siswa B lewat ID yang ditebak/diketik manual di URL.
 */
class StudentSelfAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createSiswaWithProfile(): User
    {
        $user = User::factory()->create(['role' => 'siswa', 'status' => 'active']);
        StudentProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        return $user->fresh();
    }

    // ── /student/me — IDOR-immune by design (tidak terima ID sama sekali) ──

    public function test_student_me_returns_own_profile_regardless_of_other_students_ids(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $this->createSiswaWithProfile(); // siswa B, id lebih tinggi, sekadar noise di DB.

        $response = $this->actingAs($siswaA, 'sanctum')->getJson('/api/student/me');

        $response->assertOk()->assertJsonPath('data.id', $siswaA->studentProfile->id);
    }

    // ── /students/{id} — IDOR via profile ID ────────────────────────────

    public function test_student_cannot_view_another_students_profile_by_id(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/students/{$siswaB->studentProfile->id}")
            ->assertStatus(403);
    }

    public function test_student_can_view_own_profile_by_id(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $this->actingAs($siswa, 'sanctum')
            ->getJson("/api/students/{$siswa->studentProfile->id}")
            ->assertOk();
    }

    // ── /submissions/{id} — IDOR via submission ID ──────────────────────

    public function test_student_cannot_view_another_students_submission_by_guessing_id(): void
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

    // ── /student-badges/{id} — IDOR via badge ID ────────────────────────

    public function test_student_cannot_view_another_students_badge_by_guessing_id(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $badge = StudentBadge::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/student-badges/{$badge->id}")
            ->assertStatus(403);
    }

    // ── /student-awards/{id} — IDOR via award ID ────────────────────────

    public function test_student_cannot_view_another_students_award_by_guessing_id(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $award = StudentAward::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/student-awards/{$award->id}")
            ->assertStatus(403);
    }

    // ── /certificates/{id} — IDOR via certificate ID ────────────────────

    public function test_student_cannot_view_another_students_certificate_by_guessing_id(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/certificates/{$certificate->id}")
            ->assertStatus(403);
    }

    public function test_student_can_view_own_certificate(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);

        $this->actingAs($siswa, 'sanctum')
            ->getJson("/api/certificates/{$certificate->id}")
            ->assertOk();
    }

    // ── Super Admin tetap bisa akses semua (monitoring/support) ─────────

    public function test_super_admin_can_view_any_students_records_across_all_resources(): void
    {
        $siswa = $this->createSiswaWithProfile();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $submission = ActivitySubmission::factory()->create(['student_profile_id' => $siswa->studentProfile->id]);
        $certificate = Certificate::factory()->create(['student_profile_id' => $siswa->studentProfile->id]);

        $this->actingAs($admin, 'sanctum')->getJson("/api/students/{$siswa->studentProfile->id}")->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson("/api/submissions/{$submission->id}")->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson("/api/certificates/{$certificate->id}")->assertOk();
    }

    // ── Staff (Wali Kelas/Kepala Sekolah) — scope belum ada, harus DENY ──
    // (bukan izinkan diam-diam) sampai scope rombel/sekolah benar2 dibangun.

    public function test_wali_kelas_cannot_view_student_records_until_rombel_scope_exists(): void
    {
        $siswa = $this->createSiswaWithProfile();
        $wali = User::factory()->create(['role' => 'wali_kelas']);

        $submission = ActivitySubmission::factory()->create(['student_profile_id' => $siswa->studentProfile->id]);

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/submissions/{$submission->id}")
            ->assertStatus(403);
    }

    public function test_kepala_sekolah_cannot_view_student_records_until_school_scope_exists(): void
    {
        $siswa = $this->createSiswaWithProfile();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah']);

        $certificate = Certificate::factory()->create(['student_profile_id' => $siswa->studentProfile->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/certificates/{$certificate->id}")
            ->assertStatus(403);
    }

    // ── Non-siswa tidak bisa akses /student/me ──────────────────────────

    public function test_non_siswa_gets_404_on_student_me(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/student/me')
            ->assertStatus(404);
    }
}
