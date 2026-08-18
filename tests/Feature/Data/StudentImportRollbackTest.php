<?php

namespace Tests\Feature\Data;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TEST-002 tambahan — rollback test yang secara eksplisit memicu
 * kegagalan DI TENGAH batch commit (bukan cuma cek "tidak bisa commit
 * dua kali" seperti test yang sudah ada), untuk memastikan
 * DB::transaction() di StudentImportService::commit() benar-benar
 * membatalkan SEMUA baris kalau SATU baris gagal — bukan cuma baris
 * yang gagal itu saja.
 *
 * ASUMSI: kolom `nisn` di tabel student_profiles punya unique
 * constraint di level database (bukan cuma dicek di aplikasi lewat
 * validateRows()). Kalau ternyata TIDAK ada unique constraint di
 * migration student_profiles, test ini akan gagal dengan pesan yang
 * beda (row 2 akan berhasil insert, bukan melempar exception) —
 * dalam kasus itu, tambahkan unique index pada kolom nisn karena ini
 * celah integritas data yang nyata, bukan cuma soal test.
 */
class StudentImportRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_commit_rolls_back_all_rows_when_one_row_fails_mid_batch(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $uploader = User::factory()->create(['school_id' => $school->id, 'role' => User::ROLE_SUPER_ADMIN]);

        // Baris 2 sengaja pakai NISN yang SUDAH terdaftar duluan di DB —
        // simulasi race condition: valid saat preview, tapi sudah dipakai
        // orang lain sebelum commit dijalankan.
        $conflictingNisn = '9990009999';
        $existingUser = User::factory()->create(['school_id' => $school->id, 'role' => User::ROLE_SISWA]);
        StudentProfile::factory()->create([
            'user_id' => $existingUser->id,
            'nisn' => $conflictingNisn,
        ]);

        $batch = ImportBatch::create([
            'token' => (string) Str::uuid(),
            'uploaded_by' => $uploader->id,
            'academic_year_id' => $academicYear->id,
            'original_filename' => 'rollback-test.xlsx',
            'rows_payload' => [
                [
                    'row_number' => 2,
                    'full_name' => 'Siswa Baris Pertama',
                    'nisn' => '1112223334',
                    'birth_date' => '2015-01-01',
                    'method' => StudentProfile::METHOD_DIGITAL,
                    'rombel_id' => null,
                    'is_valid' => true,
                    'errors' => [],
                ],
                [
                    'row_number' => 3,
                    'full_name' => 'Siswa Baris Kedua Konflik',
                    'nisn' => $conflictingNisn,
                    'birth_date' => '2015-02-02',
                    'method' => StudentProfile::METHOD_DIGITAL,
                    'rombel_id' => null,
                    'is_valid' => true,
                    'errors' => [],
                ],
            ],
            'total_rows' => 2,
            'valid_rows' => 2,
            'invalid_rows' => 0,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);

        $usersBefore = User::count();
        $profilesBefore = StudentProfile::count();
        $enrollmentsBefore = Enrollment::count();

        $service = app(StudentImportService::class);

        try {
            $service->commit($batch);
            $this->fail('Commit seharusnya melempar exception karena NISN baris kedua konflik.');
        } catch (\Throwable $e) {
            // Exception yang ditangkap tidak masalah jenisnya apa (bisa
            // QueryException dari unique constraint) — yang penting DIA
            // MELEMPAR, dan efeknya di database di-cek di bawah.
        }

        // Baris pertama TIDAK BOLEH ikut tersimpan meskipun row itu sendiri
        // valid — karena satu transaction mencakup seluruh batch.
        $this->assertSame($usersBefore, User::count(), 'Tidak boleh ada user baru tersisa setelah rollback.');
        $this->assertSame($profilesBefore, StudentProfile::count(), 'Tidak boleh ada student profile baru tersisa setelah rollback.');
        $this->assertSame($enrollmentsBefore, Enrollment::count(), 'Tidak boleh ada enrollment baru tersisa setelah rollback.');

        $this->assertDatabaseMissing('student_profiles', ['nisn' => '1112223334']);

        $batch->refresh();
        $this->assertSame(
            ImportBatch::STATUS_PREVIEWED,
            $batch->status,
            'Batch tidak boleh berubah jadi COMMITTED kalau transaction gagal di tengah.'
        );
        $this->assertNull($batch->committed_at);
    }
}
