<?php

namespace Tests\Feature\Data;

use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TEST-002 tambahan — regression test untuk account generation.
 *
 * CATATAN: tidak ada AccountGenerationService terpisah di project ini.
 * Logic generate username & password murni ada di dalam
 * StudentImportService::commit() (lihat generateUniqueUsername() dan
 * Hash::make(Str::random(16)) di situ) — jadi test ini menguji method
 * itu langsung, bukan service yang tidak ada.
 */
class AccountGenerationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCommittableBatch(School $school, AcademicYear $academicYear, User $uploader, array $rows): ImportBatch
    {
        return ImportBatch::create([
            'token' => (string) Str::uuid(),
            'uploaded_by' => $uploader->id,
            'academic_year_id' => $academicYear->id,
            'original_filename' => 'account-gen-test.xlsx',
            'rows_payload' => $rows,
            'total_rows' => count($rows),
            'valid_rows' => count($rows),
            'invalid_rows' => 0,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);
    }

    private function validRow(int $rowNumber, string $fullName, string $nisn): array
    {
        return [
            'row_number' => $rowNumber,
            'full_name' => $fullName,
            'nisn' => $nisn,
            'birth_date' => '2015-01-01',
            'method' => StudentProfile::METHOD_DIGITAL,
            'rombel_id' => null,
            'is_valid' => true,
            'errors' => [],
        ];
    }

    public function test_duplicate_full_name_gets_unique_suffixed_username(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $uploader = User::factory()->create(['school_id' => $school->id, 'role' => User::ROLE_SUPER_ADMIN]);

        // Dua siswa dengan nama sama persis -> username dasar sama,
        // generateUniqueUsername() harus kasih suffix beda supaya tidak bentrok.
        $batch = $this->makeCommittableBatch($school, $academicYear, $uploader, [
            $this->validRow(2, 'Ahmad Budi', '1000000001'),
            $this->validRow(3, 'Ahmad Budi', '1000000002'),
        ]);

        $service = app(StudentImportService::class);
        $created = $service->commit($batch);

        $this->assertCount(2, $created);
        $usernames = array_column($created, 'username');
        $this->assertCount(2, array_unique($usernames), 'Dua siswa nama sama harus dapat username yang berbeda.');
    }

    public function test_password_is_hashed_not_plaintext(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $uploader = User::factory()->create(['school_id' => $school->id, 'role' => User::ROLE_SUPER_ADMIN]);

        $batch = $this->makeCommittableBatch($school, $academicYear, $uploader, [
            $this->validRow(2, 'Citra Dewi', '1000000003'),
        ]);

        $service = app(StudentImportService::class);
        $created = $service->commit($batch);

        $user = User::where('username', $created[0]['username'])->firstOrFail();

        // Password bukan plaintext yang gampang ditebak, dan tervalidasi
        // sebagai hash Laravel (bcrypt/argon), bukan string acak biasa.
        $this->assertNotEmpty($user->password);
        $this->assertTrue(Hash::isHashed($user->password), 'Password harus tersimpan dalam bentuk hash.');
        $this->assertTrue($user->must_change_password, 'Akun baru wajib ganti password saat login pertama.');
    }

    public function test_commit_response_never_exposes_password(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $uploader = User::factory()->create(['school_id' => $school->id, 'role' => User::ROLE_SUPER_ADMIN]);

        $batch = $this->makeCommittableBatch($school, $academicYear, $uploader, [
            $this->validRow(2, 'Eka Fajar', '1000000004'),
        ]);

        $service = app(StudentImportService::class);
        $created = $service->commit($batch);

        // $created dikembalikan ke caller (controller -> response API) --
        // pastikan tidak ada field password/plaintext apapun di dalamnya.
        $this->assertArrayNotHasKey('password', $created[0]);
        $this->assertArrayHasKey('username', $created[0]);
        $this->assertArrayHasKey('nisn', $created[0]);
    }

    public function test_username_base_derived_from_full_name_slug(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $uploader = User::factory()->create(['school_id' => $school->id, 'role' => User::ROLE_SUPER_ADMIN]);

        $batch = $this->makeCommittableBatch($school, $academicYear, $uploader, [
            $this->validRow(2, 'Gita Hana Indah', '1000000005'),
        ]);

        $service = app(StudentImportService::class);
        $created = $service->commit($batch);

        // Str::slug('Gita Hana Indah', '') -> 'gitahanaindah'
        $this->assertSame('gitahanaindah', $created[0]['username']);
    }
}
